<?php

declare(strict_types=1);

namespace App\Tests;

use App\Dto\UserDto;
use App\Service\BillingClient;
use App\Tests\Mock\BillingClientMock;
use Exception;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use function count;
use function is_int;

abstract class AbstractTest extends WebTestCase
{
    public const ADMIN_PASSWORD = 'admin_password';
    public const USER_PASSWORD = 'user_password';
    public const USER_EMAIL = 'user@example.com';
    public const ADMIN_EMAIL = 'admin@example.com';

    protected static KernelBrowser $client;

    protected function setUp(): void
    {
        static::$client = static::createClient();
    }

    protected static function getClient($reinitialize = false): KernelBrowser
    {
        if ($reinitialize) {
            static::$client = static::createClient();
        }

        // core is loaded (for tests without calling of getClient(true))
//        static::$client->getKernel()->boot();

        return static::$client;
    }

    /**
     * Shortcut
     */
    protected static function getEntityManager()
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    public function assertResponseOk(?Response $response = null, ?string $message = null, string $type = 'text/html')
    {
        $this->failOnResponseStatusCheck($response, 'isOk', $message, $type);
    }

    public function assertResponseRedirect(
        ?Response $response = null,
        ?string   $message = null,
        string    $type = 'text/html'
    ) {
        $this->failOnResponseStatusCheck($response, 'isRedirect', $message, $type);
    }

    public function assertResponseNotFound(
        ?Response $response = null,
        ?string   $message = null,
        string    $type = 'text/html'
    ) {
        $this->failOnResponseStatusCheck($response, 'isNotFound', $message, $type);
    }

    public function assertResponseForbidden(
        ?Response $response = null,
        ?string   $message = null,
        string    $type = 'text/html'
    ) {
        $this->failOnResponseStatusCheck($response, 'isForbidden', $message, $type);
    }

    public function assertResponseCode(
        int       $expectedCode,
        ?Response $response = null,
        ?string   $message = null,
        string    $type = 'text/html'
    ) {
        $this->failOnResponseStatusCheck($response, $expectedCode, $message, $type);
    }
    /**
     * @param Response $response
     * @param string   $type
     *
     * @return string
     */
    public function guessErrorMessageFromResponse(Response $response, string $type = 'text/html')
    {
        try {
            $crawler = new Crawler();
            $crawler->addContent($response->getContent(), $type);

            if (!count($crawler->filter('title'))) {
                $add = '';
                $content = $response->getContent();

                if ('application/json' === $response->headers->get('Content-Type')) {
                    $data = json_decode($content);
                    if ($data) {
                        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $add = ' FORMATTED';
                    }
                }
                $title = '[' . $response->getStatusCode() . ']' . $add .' - ' . $content;
            } else {
                $title = $crawler->filter('title')->text();
            }
        } catch (Exception $e) {
            $title = $e->getMessage();
        }

        return trim($title);
    }

    private function failOnResponseStatusCheck(
        Response $response = null,
        $func = null,
        ?string $message = null,
        string $type = 'text/html'
    ) {
        if (null === $func) {
            $func = 'isOk';
        }

        if (null === $response && self::$client) {
            $response = self::$client->getResponse();
        }

        try {
            if (is_int($func)) {
                $this->assertEquals($func, $response->getStatusCode());
            } else {
                $this->assertTrue($response->{$func}());
            }

            return;
        } catch (Exception $e) {
            // nothing to do
        }

        $err = $this->guessErrorMessageFromResponse($response, $type);
        if ($message) {
            $message = rtrim($message, '.') . ". ";
        }

        if (is_int($func)) {
            $template = "Failed asserting Response status code %s equals %s.";
        } else {
            $template = "Failed asserting that Response[%s] %s.";
            $func = preg_replace('#([a-z])([A-Z])#', '$1 $2', $func);
        }

        $message .= sprintf($template, $response->getStatusCode(), $func, $err);

        $max_length = 100;
        if (mb_strlen($err, 'utf-8') < $max_length) {
            $message .= " " . $this->makeErrorOneLine($err);
        } else {
            $message .= " " . $this->makeErrorOneLine(mb_substr($err, 0, $max_length, 'utf-8') . '...');
            $message .= "\n\n" . $err;
        }

        $this->fail($message);
    }

    private function makeErrorOneLine($text)
    {
        return preg_replace('#[\n\r]+#', ' ', $text);
    }

    private static array $usersByEmail = [
        self::USER_EMAIL => [self::USER_PASSWORD, 'user_token'],
        self::ADMIN_EMAIL => [self::ADMIN_PASSWORD, 'admin_token']
    ];

    protected function mockBillingClient(KernelBrowser $client): void
    {
        $client->disableReboot();

        $billingClientMock = $this->getMockBuilder(BillingClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $billingClientMock->method('auth')
            ->willReturnCallback(static function (array $credentials) {
                $email = $credentials['username'];
                if (isset(self::$usersByEmail[$email])) {
                    return self::$usersByEmail[$email][1];
                }
                throw new CustomUserMessageAuthenticationException('Неправильные логин или пароль');
            });

        $billingClientMock->method('register')
            ->willReturnCallback(static function (array $credentials) {
                $email = $credentials['username'];
                if (isset(self::$usersByEmail[$email])) {
                    throw new CustomUserMessageAuthenticationException('Пользователь с указанным email уже существует!');
                }
                self::$usersByEmail[$email] = [$credentials['password'], 'user_token2'];
                return 'user_token2';
            });

        $billingClientMock->method('getCurrent')
            ->willReturnMap([
                ['user_token', new UserDto(self::USER_EMAIL, ['ROLE_USER'], 1000)],
                ['user_token2', new UserDto('test@example.com', ['ROLE_USER'], 0)],
                ['admin_token', new UserDto(self::ADMIN_EMAIL, ['ROLE_SUPER_ADMIN'], 0)],
            ]);

        static::getContainer()->set(BillingClient::class, $billingClientMock);
    }

    protected function authorize(AbstractBrowser $client, string $login, string $password): ?Crawler
    {
        $crawler = $client->clickLink('Вход');

        $form = $crawler->filter('form')->first()->form();
        $form['email'] = $login;
        $form['password'] = $password;

        $crawler = $client->submit($form);
        $this->assertResponseOk();

        return $crawler;
    }

    public function checkProfile(?AbstractBrowser $client, string $email, string $role, string $balance): Crawler
    {
        $crawler = $client->clickLink('Профиль');
        $this->assertResponseOk();

        $profileData = $crawler->filter('td')->each(function ($node, $i) {
            return $node->text();
        });

        self::assertEquals($email, $profileData[1]);
        self::assertEquals($role, $profileData[3]);
        self::assertEquals($balance, $profileData[5]);
        return $crawler;
    }

    // Только для дебага
    protected static function ddBody(Crawler $crawler): void
    {
        dd($crawler->filter('body')->html());
    }
}
