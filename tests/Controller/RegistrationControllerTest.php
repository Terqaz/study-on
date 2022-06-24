<?php

namespace App\Tests\Controller;

use App\Tests\AbstractTest;
use App\Tests\TestUtils;

class RegistrationControllerTest extends AbstractTest
{
    public function testRegister(): void
    {
        $client = static::getClient();

        $this->mockBillingClient($client);

        $client->request('GET', '/courses/');
        $crawler = $client->clickLink('Регистрация');
        $form = $crawler->filter('form')->first()->form();

        $email = 'test@example.com';
        $password = 'test_password';

        // Нет пароля
        $form['registration_form[email]'] = $email;
        $crawler = $client->submit($form);
        $this->assertResponseOk(); // Вернулась форма регистрации с ошибкой
        self::assertEquals('Пожалуйста, придумайте пароль', $crawler->filter('.invalid-feedback')->text());

        // Пароли не совпали
        $form['registration_form[email]'] = $email;
        $form['registration_form[password][first]'] = $password;
        $form['registration_form[password][second]'] = $password . '1';
        $crawler = $client->submit($form);
        $this->assertResponseOk(); // Вернулась форма регистрации с ошибкой
        self::assertEquals('Пароли должны совпадать', $crawler->filter('.invalid-feedback')->text());

        // Такой логин уже существует
        $form['registration_form[email]'] = AbstractTest::USER_EMAIL;
        $form['registration_form[password][first]'] = $password;
        $form['registration_form[password][second]'] = $password;
        $crawler = $client->submit($form);
        $this->assertResponseOk(); // Вернулась форма регистрации с ошибкой
        self::assertEquals('Пользователь с указанным email уже существует!', $crawler->filter('.alert')->text());

        // Все верно
        $client->followRedirects();
        $form['registration_form[email]'] = $email;
        $form['registration_form[password][first]'] = $password;
        $form['registration_form[password][second]'] = $password;
        $crawler = $client->submit($form);
        self::assertRouteSame('app_course_index');

        $this->checkProfile($client, $email, 'ROLE_USER', 0);
        $client->clickLink('Выход');
        $this->assertResponseOk();

        // Входим еще раз
        $this->authorize($client, $email, $password);
        $this->checkProfile($client, $email, 'ROLE_USER', 0);

        $client->clickLink('Выход');
        $this->assertResponseOk();
    }
}
