<?php

namespace App\Tests\Controller;

use App\Tests\AbstractTest;
use App\Tests\TestUtils;

class LoginControllerTest extends AbstractTest
{
    public function testLogin(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $crawler = $client->request('GET', '/');

        // Входим как обычный пользователь
        $email = AbstractTest::USER_EMAIL;
        $this->authorize($client, $email, AbstractTest::USER_PASSWORD);
        $this->checkProfile($client, $email, 'ROLE_USER', 1000);

        $client->clickLink('Выход');
        $this->assertResponseOk();

        // Входим как админ
        $email = AbstractTest::ADMIN_EMAIL;
        $this->authorize($client, $email, AbstractTest::ADMIN_PASSWORD);
        $this->checkProfile($client, $email, 'ROLE_SUPER_ADMIN', 0);

        $client->clickLink('Выход');
        $this->assertResponseOk();
    }
}
