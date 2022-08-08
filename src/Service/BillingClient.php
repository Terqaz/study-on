<?php

namespace App\Service;

use App\Dto\CourseDto;
use App\Dto\UserDto;
use App\Exception\BillingUnavailableException;
use App\Exception\CourseAlreadyPaidException;
use App\Exception\InsufficientFundsException;
use App\Exception\ResourceAlreadyExistsException;
use App\Exception\ResourceNotFoundException;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use JsonException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BillingClient
{
    protected const GET = 'GET';
    protected const POST = 'POST';
    protected const BAD_JWT_TOKEN = 'Необходимо войти заново';

    private ValidatorInterface $validator;
    private Serializer $serializer;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->serializer = SerializerBuilder::create()->build();
    }

    /**
     * @param array $credentials - ['username' => ..., 'password' => ...]
     * @return array ['token' => JWT token, 'refresh_token' => ...]
     * @throws BillingUnavailableException
     * @throws JsonException
     */
    public function auth(array $credentials): array
    {
        $response = $this->jsonRequest(
            self::POST,
            '/auth',
            [],
            $credentials
        );
        if ($response['code'] === 401) {
            throw new CustomUserMessageAuthenticationException('Неправильные логин или пароль');
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }
        return $this->parseJsonResponse($response);
    }

    /**
     * @param array $credentials - ['username' => ..., 'password' => ...]
     * @return array ['token' => JWT token, 'refresh_token' => ...]
     * @throws BillingUnavailableException
     * @throws JsonException
     */
    public function register(array $credentials): array
    {
        $response = $this->jsonRequest(
            self::POST,
            '/register',
            [],
            $credentials
        );

        if ($response['code'] === 409) {
            throw new CustomUserMessageAuthenticationException('Пользователь с указанным email уже существует!');
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }

        return $this->parseJsonResponse($response);
    }

    /**
     * @param string $token - JWT token
     * @return UserDto - user data
     * @throws BillingUnavailableException|JsonException
     */
    public function getCurrent(string $token): UserDto
    {
        $response = $this->jsonRequest(
            self::GET,
            '/users/current',
            [],
            [],
            ['Authorization' => 'Bearer ' . $token]
        );
        if ($response['code'] === 401) {
            throw new UnauthorizedHttpException(self::BAD_JWT_TOKEN);
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }

        $userDto = $this->parseJsonResponse($response, UserDto::class);
        $errors = $this->validator->validate($userDto);
        if (count($errors) > 0) {
            throw new BillingUnavailableException('User data is not valid');
        }
        return $userDto;
    }

    /** Get new access and refresh tokens
     * @param string $refreshToken
     * @return array - ['token' => JWT token, 'refresh_token' => ...]
     * @throws BillingUnavailableException|JsonException
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = $this->jsonRequest(
            self::POST,
            '/token/refresh',
            [],
            ['refresh_token' => $refreshToken],
        );
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }

        return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws BillingUnavailableException
     * @throws JsonException
     */
    public function getCourses(): array
    {
        $response = $this->jsonRequest(
            self::GET,
            '/courses'
        );

        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }

        return $this->parseJsonResponse($response);
    }

    /**
     * @throws BillingUnavailableException
     * @throws JsonException
     * @throws ResourceNotFoundException
     */
    public function getCourse(string $code): array
    {
        $response = $this->jsonRequest(
            self::GET,
            '/courses/' . $code
        );

        if ($response['code'] === 404) {
            throw new ResourceNotFoundException('Курс не найден');
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }

        return $this->parseJsonResponse($response);
    }

    /**
     * @throws BillingUnavailableException
     * @throws JsonException
     * @throws ResourceNotFoundException
     * @throws InsufficientFundsException
     * @throws CourseAlreadyPaidException
     */
    public function payCourse(string $token, string $code): array
    {
        $response = $this->jsonRequest(
            self::POST,
            '/courses/' . $code . '/pay',
            [],
            [],
            ['Authorization' => 'Bearer ' . $token]
        );

        switch ($response['code']) {
            case 401:
                throw new UnauthorizedHttpException(self::BAD_JWT_TOKEN);
            case 404:
                throw new ResourceNotFoundException();
            case 406:
                throw new InsufficientFundsException();
            case 409:
                throw new CourseAlreadyPaidException();
            default:
                break;
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }

        return $this->parseJsonResponse($response);
    }

    /**
     * @throws BillingUnavailableException
     * @throws JsonException
     */
    public function getTransactions(
        string  $token,
        ?string $transactionType = null,
        ?string $courseCode = null,
        bool    $skipExpired = false
    ): array {
        $parameters = [];

        if (null !== $transactionType) {
            $parameters['filter[type]'] = $transactionType;
        }
        if (null !== $courseCode) {
            $parameters['filter[course_code]'] = $courseCode;
        }
        if ($skipExpired) {
            $parameters['filter[skip_expired]'] = $skipExpired;
        }

        $response = $this->jsonRequest(
            self::GET,
            '/transactions',
            $parameters,
            [],
            ['Authorization' => 'Bearer ' . $token]
        );

        if ($response['code'] === 401) {
            throw new UnauthorizedHttpException(self::BAD_JWT_TOKEN);
        }
        if ($response['code'] >= 400) {
            throw new BillingUnavailableException();
        }

        return $this->parseJsonResponse($response);
    }

    /**
     * @throws ResourceNotFoundException
     * @throws BillingUnavailableException
     * @throws ResourceAlreadyExistsException
     * @throws JsonException
     */
    public function saveCourse(string $token, CourseDto $course, string $code = null): bool
    {
        $path = '/courses';
        if (null !== $code) {
            $path .= "/$code";
        }

        $response = $this->jsonRequest(
            self::POST,
            $path,
            [],
            $course,
            ['Authorization' => 'Bearer ' . $token]
        );
        switch ($response['code']) {
            case 401:
                throw new UnauthorizedHttpException(self::BAD_JWT_TOKEN);
            case 403:
                throw new AccessDeniedHttpException();
            case 404:
                throw new ResourceNotFoundException('Курс не найден');
            case 409:
                throw new ResourceAlreadyExistsException('Курс с данным кодом уже существует');
            default:
                break;
        }

        $body = $this->parseJsonResponse($response);

        if ($response['code'] >= 400) {
            throw new BillingUnavailableException($body['error']);
        }
        return $body['success'];
    }

    /**
     * @throws BillingUnavailableException
     * @throws JsonException
     */
    public function isCoursePaid(string $apiToken, array $billingCourse): bool
    {
        if ($billingCourse['type'] === 'free') {
            return true;
        }
        $transaction = $this->getTransactions(
            $apiToken,
            'payment',
            $billingCourse['code'],
            true
        );
        return count($transaction) > 0;
    }

    /**
     * @throws JsonException
     */
    protected function parseJsonResponse(array $response, ?string $type = null)
    {
        if (null === $type) {
            return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        }
        return $this->serializer->deserialize($response['body'], $type, 'json');
    }

    /**
     * @throws BillingUnavailableException
     * @throws JsonException
     */
    protected function jsonRequest(
        string $method,
        string $path,
        array  $parameters = [],
        $data = [],
        array  $headers = []
    ): array {
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';

        return $this->request($method, $path, $parameters, $this->serializer->serialize($data, 'json'), $headers);
    }

    /**
     * @param string $method - HTTP method
     * @param string $path - path, relative to billing host
     * @param array $parameters - query parameters
     * @param array|string $body - HTTP body. Sets in request only if method is POST
     * @param array $headers - HTTP headers
     * @return array - response code and body
     * @throws BillingUnavailableException
     */
    protected function request(
        string       $method,
        string       $path,
        array        $parameters = [],
        $body = '',
        array        $headers = []
    ): array {
        if (count($parameters) > 0) {
            $path .= '?';

            $newParameters = [];
            foreach ($parameters as $name => $value) {
                $newParameters[] = $name . '=' . $value;
            }
            $path .= implode('&', $newParameters);
        }

        $ch = curl_init("http://billing.study-on.local/api/v1" . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method === self::POST && !empty($body)) {
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
