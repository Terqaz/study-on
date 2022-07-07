<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Service\BillingClient;
use App\Tests\AbstractTest;
use App\Tests\TestUtils;

class LessonControllerTest extends AbstractTest
{
    public function testShowLesson(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $billingClientMock = $this->mockBillingClient($client, false);

        $billingClientMock->method('getTransactions')
            ->willReturn([self::$transactions[1]]); // Курс изначально куплен

        static::getContainer()->set(BillingClient::class, $billingClientMock);

        // Вход
        $client->request('GET', '/');
        $this->authorize($client, AbstractTest::USER_EMAIL, AbstractTest::USER_PASSWORD);
        $client->request('GET', '/courses/1');

        $crawler = $client->clickLink('Операторы. Переменные. Типы данных. Условия');
        $this->assertResponseOk();

        // Проверка заголовка
        self::assertEquals(
            'Операторы. Переменные. Типы данных. Условия',
            $crawler->filter('h1')->text()
        );
    }

    /**
     * @depends testShowLesson
     */
    public function testShowLessonFailed(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $client->request('GET', '/');
        $this->authorize($client, AbstractTest::USER_EMAIL, AbstractTest::USER_PASSWORD);
        $client->request('GET', '/courses/1');

        // Без покупки нет ссылок на курсы
        $this->expectException('InvalidArgumentException');
        $crawler = $client->clickLink('Операторы. Переменные. Типы данных. Условия');

        // И доступа
        $client->request('GET', '/lessons/1');
        $this->assertResponseCode(403);

        // Если несуществующий айдишник
        $client->request('GET', '/lessons/34636643');
        $this->assertResponseCode(404);

        // Если вместо айдишника буквы
        $client->request('GET', '/lessons/sdsb');
        $this->assertResponseCode(500);
    }

    public function testSubmitNewLesson(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $course = $courseRepository->find(1);
        $oldCount = $course->getLessons()->count();

        $client->request('GET', '/');
        $this->authorize($client, AbstractTest::ADMIN_EMAIL, AbstractTest::ADMIN_PASSWORD);
        $client->request('GET', '/courses/1');

        $crawler = $client->clickLink('Добавить урок');

        $form = $crawler
            ->selectButton('Создать урок')
            ->form();

        // Проверка присутствия полей
        self::assertFalse($form->has('lesson[id]'));
        self::assertEquals($course->getId(), (int)$form['lesson[course_id]']->getValue());
        self::assertTrue($form->has('lesson[name]'));
        self::assertTrue($form->has('lesson[content]'));
        self::assertTrue($form->has('lesson[serialNumber]'));

        // Если не указано имя
        $client->submitForm('Создать урок', [
            'lesson[content]' => 'Тестовое описание урока',
            'lesson[serialNumber]' => '4'
        ]);
        $this->assertResponseCode(500);

        // Если не указано описание
        $client->back();
        $crawler = $client->submitForm('Создать урок', [
            'lesson[name]' => 'Тестовый урок',
            'lesson[serialNumber]' => '4'
        ]);
        $this->assertResponseCode(500);

        // Все указано правильно и без серийного номера
        $client->back();
        $client->submitForm('Создать урок', [
            'lesson[name]' => 'Тестовый урок',
            'lesson[content]' => 'Тестовое описание урока',
        ]);
        $this->assertResponseOk();
        self::assertRouteSame('app_course_show');

        // Все указано правильно
        $client->back();
        $client->submitForm('Создать урок', [
            'lesson[name]' => 'Тестовый урок',
            'lesson[content]' => 'Тестовое описание урока',
            'lesson[serialNumber]' => '1'
        ]);
        $this->assertResponseOk();

        // В итоге добавилось 2 урока
        self::assertEquals($oldCount + 2, $courseRepository->find(1)->getLessons()->count());
    }

    public function testSubmitNewLessonFailed(): void
    {
        $client = static::getClient();

        $this->mockBillingClient($client);

        $client->request('GET', '/courses/1');

        // Неавторизован
        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $crawler = $client->clickLink('Добавить урок');

        $client->request('GET', '/lessons/new/');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/new/', ['course_id' => 1]);
        self::assertResponseRedirects('/login');

        // Авторизован как обычный пользователь
        $client->followRedirects();
        $crawler = $client->request('GET', '/courses/1');
        $this->authorize($client, AbstractTest::USER_EMAIL, AbstractTest::USER_PASSWORD);

        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $crawler = $client->clickLink('Добавить урок');

        $client->request('GET', '/lessons/new');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/new', ['course_id' => 1]);
        $this->assertResponseCode(403);
    }

    /**
     * @depends testShowLesson
     */
    public function testEditLesson(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $lessonRepository = self::getEntityManager()->getRepository(Lesson::class);

        $client->request('GET', '/');
        $this->authorize($client, AbstractTest::ADMIN_EMAIL, AbstractTest::ADMIN_PASSWORD);
        $client->request('GET', '/lessons/1');

        $crawler = $client->clickLink('Изменить урок');

        // Кнопка обновления не привязана к форме, поэтому получим форму по-другому
        $form = $crawler->filter('form')->first()->form();

        // Проверка заполненненных полей
        $values = $form->getValues();
        $lessonId = 1;
        $lesson = $lessonRepository->find($lessonId);

        $courseId = $lesson->getCourse()->getId();
        self::assertEquals($courseId, $values['lesson[course_id]']);
        self::assertEquals($lesson->getName(), $values['lesson[name]']);
        self::assertEquals($lesson->getContent(), $values['lesson[content]']);
        self::assertEquals($lesson->getSerialNumber(), $values['lesson[serialNumber]']);

        $name = 'Тестовый урок';
        $content = 'Контент урока';
        $serialNumber = 7;

        // Сохранение обновленного курса
        $form['lesson[name]'] = $name;
        $form['lesson[content]'] = $content;
        $form['lesson[serialNumber]'] = $serialNumber;
        $client->submit($form);

        $this->assertResponseOk();
        self::assertRouteSame('app_lesson_show');

        // Проверка сохраненного курса
        $lesson = $lessonRepository->find($lessonId);
        self::assertEquals($courseId, $lesson->getCourse()->getId());
        self::assertEquals($name, $lesson->getName());
        self::assertEquals($content, $lesson->getContent());
        self::assertEquals($serialNumber, $lesson->getSerialNumber());
    }

    public function testEditLessonFailed(): void
    {
        $client = static::getClient();

        $this->mockBillingClient($client);

        $client->request('GET', '/lessons/1');

        // Неавторизован
        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $crawler = $client->clickLink('Изменить урок');

        $client->request('GET', '/lessons/1/edit');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/1/edit', ['course_id' => 1]);
        self::assertResponseRedirects('/login');

        // Авторизован как обычный пользователь
        $client->followRedirects();
        $crawler = $client->request('GET', '/lessons/1');
        $this->authorize($client, AbstractTest::USER_EMAIL, AbstractTest::USER_PASSWORD);

        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $crawler = $client->clickLink('Изменить урок');

        $client->request('GET', '/lessons/1/edit');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/1/edit', ['course_id' => 1]);
        $this->assertResponseCode(403);
    }

    /**
     * @depends testShowLesson
     */
    public function testDeleteLesson(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $lessonRepository = self::getEntityManager()->getRepository(Lesson::class);

        $client->request('GET', '/');
        $this->authorize($client, AbstractTest::ADMIN_EMAIL, AbstractTest::ADMIN_PASSWORD);
        $client->request('GET', '/lessons/1');
        $client->submitForm('Удалить');
        $this->assertResponseOk();

        self::assertRouteSame('app_course_show');

        self::assertNull($lessonRepository->find(1));
    }

    public function testDeleteLessonFailed(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $client->request('GET', '/lessons/1');

        // Неавторизован
        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $crawler = $client->clickLink('Удалить');

        $client->request('POST', '/courses/1/delete');
        self::assertResponseRedirects('/login');

        // Авторизован как обычный пользователь
        $client->followRedirects();
        $crawler = $client->request('GET', '/lessons/1');
        $this->authorize($client, AbstractTest::USER_EMAIL, AbstractTest::USER_PASSWORD);

        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $crawler = $client->clickLink('Удалить');

        $client->request('POST', '/lessons/1/delete');
        $this->assertResponseCode(403);
    }
}
