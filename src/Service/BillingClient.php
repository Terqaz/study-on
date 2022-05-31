<?php

namespace App\Service;

use App\Dto\UserDto;
use App\Exception\BillingUnavailableException;
use JMS\Serializer\SerializerBuilder;
use JsonException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BillingClient
{
    protected const GET = 'GET';
    protected const POST = 'POST';

    private ValidatorInterface $validator;

    /**
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->serializer = SerializerBuilder::create()->build();
    }

    /**
     * @param array $credentials - ['username' => ..., 'password' => ...]
     * @return ?string JWT token
     * @throws BillingUnavailableException
     * @throws JsonException
     */
    public function auth(array $credentials): string
    {
        $response = $this->jsonRequest(
            self::POST,
            '/auth',
            json_encode($credentials, JSON_THROW_ON_ERROR),
        );
        if ($response['code'] === 401) {
            throw new CustomUserMessageAuthenticationException('Неправильные логин или пароль');
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }
        return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)['token'];
    }

    /**
     * @param array $credentials - ['username' => ..., 'password' => ...]
     * @return string JWT token
     * @throws BillingUnavailableException
     * @throws JsonException
     */
    public function register(array $credentials): string
    {
        $this->request(
            self::POST,
            '/register',
            json_encode($credentials, JSON_THROW_ON_ERROR),
        );

        return ''; //TODO
    }

    /**
     * @param string $token - JWT token
     * @return UserDto - user data
     * @throws BillingUnavailableException
     */
    public function getCurrent(string $token): UserDto
    {
        $response = $this->jsonRequest(
            self::GET,
            '/users/current',
            '',
            ['Authorization' => 'Bearer ' . $token]
        );
        if ($response['code'] === 401) {
            throw new CustomUserMessageAuthenticationException('Некорректный JWT токен');
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }
//        dd($response);
        $userDto = $this->serializer->deserialize($response['body'], UserDto::class, 'json');
        $errors = $this->validator->validate($userDto);
        if (count($errors) > 0) {
            throw new BillingUnavailableException('User data is not valid');
        }
        return $userDto;
    }

    /**
     * @throws BillingUnavailableException
     */
    protected function jsonRequest(string $method, string $path, $body, array $headers = []): array
    {
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        return $this->request($method, $path, $body, $headers);
    }

    /**
     * @param string $method - HTTP method
     * @param string $path - path, relative to billing host
     * @param string|array $body - HTTP body. Sets in HTTP only if method is POST
     * @param array $headers - HTTP headers
     * @return array - raw response
     * @throws BillingUnavailableException
     */
    protected function request(string $method, string $path, $body, array $headers = []): array
    {
        $ch = curl_init("http://billing.study-on.local/api/v1" . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method === self::POST) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (count($headers) > 0) {
            $curlHeaders = [];
            foreach ($headers as $name => $value) {
                $curlHeaders[] = $name . ': ' . $value;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        $response = curl_exec($ch);
        if (curl_error($ch)) {
            throw new BillingUnavailableException(curl_error($ch));
        }
        $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [
            'code' => $responseCode,
            'body' => $response,
        ];
    }
}
