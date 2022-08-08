<?php

declare(strict_types=1);

namespace App\Tests;

use App\Dto\UserDto;
use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Exception;
use JsonException;
use PHPUnit\Framework\MockObject\MockObject;
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

    protected const COURSES = [
        [
            'code' => 'interactive-sql-trainer',
            'type' => 'free'
        ], [
            'code' => 'python-programming',
            'type' => 'rent',
            'price' => 10
        ], [
            'code' => 'building-information-modeling',
            'type' => 'buy',
            'price' => 20
        ]
    ];

    protected static KernelBrowser $client;

    protected static DateTime $expiresAtDateTime;
    private static string $createdAt;
    protected static string $expiresAt;

    protected static array $transactions;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$expiresAtDateTime = (new DateTime())->add(new DateInterval('P7D'));
        self::$createdAt = (new DateTime())->format(DateTimeInterface::ATOM);
        self::$expiresAt = self::$expiresAtDateTime->format(DateTimeInterface::ATOM);

        self::$transactions = [
            [
                "id" => 1,
                "created_at" => self::$createdAt,
                "type" => "deposit",
                "amount" => 1000
            ], [
                "id" => 2,
                "created_at" => self::$createdAt,
                "expires_at" => self::$expiresAt,
                "type" => "payment",
                "course_code" => "python-programming",
                "amount" => 10
            ], [
                "id" => 3,
                "created_at" => self::$createdAt,
                "type" => "payment",
                "course_code" => "building-information-modeling",
                "amount" => 20
            ]
        ];
    }

    protected function setUp(): void
    {
        static::$client = static::createClient();
//        self::markTestSkipped();
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

    private const USER_REFRESH_TOKEN = 'user_refresh_token';
    private const USER_REFRESH_TOKEN_2 = 'user_refresh_token2';
    private const ADMIN_REFRESH_TOKEN = 'admin_refresh_token';

    private static $usersByEmail;

    /**
     * Если $isMockFinal - false, то возвратится billingClientMock, в котором можно продолжить
     * конфигурировать моки на методы.
     * В конце нужно заменить BillingClient на billingClientMock:
     *      static::getContainer()->set(BillingClient::class, $billingClientMock);
     * @param KernelBrowser $client
     * @param bool $isMockFinal
     * @return MockObject|null
     * @throws JsonException
     */
    protected function mockBillingClient(KernelBrowser $client, bool $isMockFinal = true): ?MockObject
    {
        $newUserEmail = 'test@example.com';
        
        $userToken = self::generateTestJwt(self::USER_EMAIL);
        $userToken2 = self::generateTestJwt($newUserEmail);
        $adminToken = self::generateTestJwt(self::ADMIN_EMAIL);

        self::$usersByEmail = [
            self::USER_EMAIL => [self::USER_PASSWORD, ['token' => $userToken, 'refresh_token' => self::USER_REFRESH_TOKEN]],
            self::ADMIN_EMAIL => [self::ADMIN_PASSWORD, ['token' => $adminToken, 'refresh_token' => self::ADMIN_REFRESH_TOKEN]]
        ];

        $client->disableReboot();

        $billingClientMock = $this->getMockBuilder(BillingClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request', 'auth', 'register', 'refreshToken', 'getCurrent', 'getCourses',
                'getCourse', 'payCourse', 'getTransactions', 'saveCourse'])
            ->getMock();

        // Гарантия, что заглушка не обратится к биллингу
        $billingClientMock->method('request')
            ->willThrowException(new Exception('Bad mock'));

        $billingClientMock->method('auth')
            ->willReturnCallback(static function (array $credentials) {
                $email = $credentials['username'];
                if (isset(self::$usersByEmail[$email])) {
                    return self::$usersByEmail[$email][1];
                }
                throw new CustomUserMessageAuthenticationException('Неправильные логин или пароль');
            });

        $billingClientMock->method('register')
            ->willReturnCallback(static function (array $credentials) use ($userToken2) {
                $email = $credentials['username'];
                if (isset(self::$usersByEmail[$email])) {
                    throw new CustomUserMessageAuthenticationException('Пользователь с указанным email уже существует!');
                }

                $tokens = ['token' => $userToken2, 'refresh_token' => self::USER_REFRESH_TOKEN_2];
                self::$usersByEmail[$email] = [$credentials['password'], $tokens];
                return $tokens;
            });

        $billingClientMock->method('refreshToken')
            ->willReturnCallback(static function (string $refreshToken) {
                $users = array_filter(self::$usersByEmail, static function ($user, $email) use ($refreshToken) {
                    return $user[1]['refresh_token'] === $refreshToken;
                });

                if (count($users) > 0) {
                    return $users[0][1];
                }
                throw new BillingUnavailableException();
            });

        $billingClientMock->method('getCurrent')
            ->willReturnMap([
                [$userToken, new UserDto(self::USER_EMAIL, ['ROLE_USER'], 1000)],
                [$userToken2 , new UserDto($newUserEmail, ['ROLE_USER'], 0)],
                [$adminToken, new UserDto(self::ADMIN_EMAIL, ['ROLE_SUPER_ADMIN'], 0)],
            ]);

        $billingClientMock->method('getCourses')
            ->willReturn(self::COURSES);

        $coursesByCode = [];
        foreach (self::COURSES as $course) {
            $coursesByCode[$course['code']] = $course;
        }

        $billingClientMock->method('getCourse')
            ->willReturnCallback(static function (string $code) use ($coursesByCode) {
                return $coursesByCode[$code];
            });

        if (!$isMockFinal) {
            return $billingClientMock;
        }

        $billingClientMock->method('payCourse')
            ->willReturn([
                'success' => false
            ]);

        $billingClientMock->method('getTransactions')
            ->willReturn([]);

        $billingClientMock->method('saveCourse')
            ->willReturn(true);

        static::getContainer()->set(BillingClient::class, $billingClientMock);
        return null;
    }

    private static function generateTestJwt(string $email): string
    {
        return 'header.' . base64_encode(json_encode([
                'exp' => (new DateTime())->getTimestamp() + 10**6, // Во время теста не закончится
                'username' => $email
            ], JSON_THROW_ON_ERROR)) . '.trailer';
    }

    // Только для дебага
    protected static function ddBody(Crawler $crawler): void
    {
        var_dump($crawler->filter('body')->html());
    }
}
