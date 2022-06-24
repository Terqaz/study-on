<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Tests\AbstractTest;
use App\Tests\TestUtils;

class CourseControllerTest extends AbstractTest
{
    public function testRedirectToCourses(): void
    {
        $client = static::getClient();
        $client->request('GET', '/');
        $this->assertResponseRedirect();

        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        self::assertRouteSame('app_course_index');
    }

    public function testGetCoursesIndex(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseOk();

        // Проверка количества курсов
        self::assertEquals(
            self::getEntityManager()->getRepository(Course::class)->count([]),
            $crawler->filter(".card")->count()
        );
    }

    public function testShowCourse(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $client->request('GET', '/courses');
        $crawler = $client->clickLink('Пройти');
        $this->assertResponseOk();

        // Проверка заголовка
        self::assertEquals(
            'Программирование на Python',
            $crawler->filter('h1')->text()
        );
        // Проверка количества выведенных уроков курса
        $lessons = static::getEntityManager()
            ->getRepository(Course::class)
            ->find(1)
            ->getLessons();
        self::assertEquals($lessons->count(), $crawler->filter('ol li')->count());

        // Если несуществующий айдишник
        $client->request('GET', '/courses/34636643');
        $this->assertResponseCode(404);

        // Если вместо айдишника буквы
        $client->request('GET', '/courses/sdsb');
        $this->assertResponseCode(500);
    }

    public function testSubmitNewCourse(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $oldCount = $courseRepository->count([]);

        $client->request('GET', '/courses');

        // Вход
        $this->authorize($client, AbstractTest::ADMIN_EMAIL, AbstractTest::ADMIN_PASSWORD);
        $crawler = $client->clickLink('Добавить курс');
        $this->assertResponseOk();

        $form = $crawler
            ->selectButton('Создать курс')
            ->form();

        // Проверка присутствия полей
        self::assertFalse($form->has('course[id]'));
        self::assertTrue($form->has('course[code]'));
        self::assertTrue($form->has('course[name]'));
        self::assertTrue($form->has('course[description]'));

        // Если не указан код
        $crawler = $client->submitForm('Создать курс', [
            'course[name]' => 'Тестовый курс',
            'course[description]' => 'Описание тестового курса'
        ]);
        $this->assertResponseCode(500);

        // Если не указано имя
        $client->back();
        $crawler = $client->submitForm('Создать курс', [
            'course[code]' => 'test',
            'course[description]' => 'Описание тестового курса'
        ]);
        $this->assertResponseCode(500);

        // Если не указано описание
        $client->back();
        $crawler = $client->submitForm('Создать курс', [
            'course[code]' => 'test-1',
            'course[name]' => 'Тестовый курс1'
        ]);
        $this->assertResponseOk();  // нет ошибки
        self::assertRouteSame('app_course_index');

        // Указано всё
        $client->back();
        $client->submitForm('Создать курс', [
            'course[code]' => 'test-2',
            'course[name]' => 'Тестовый курс 2',
            'course[description]' => 'Описание тестового курса 2'
        ]);
        $this->assertResponseOk();

        // Создание курса с тем же кодом невозможно
        $client->back();
        $client->submitForm('Создать курс', [
            'course[code]' => 'test-2',
            'course[name]' => 'Тестовый курс 3',
            'course[description]' => 'Описание тестового курса 3'
        ]);
        $this->assertResponseCode(500);

        // В итоге добавилось 2 курса
        self::assertEquals($oldCount + 2, $courseRepository->count([]));
    }

    public function testSubmitNewCourseFailed(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $client->request('GET', '/courses');
        $this->assertResponseOk();

        // Неавторизован
        // Без авторизации нет кнопки
        $this->expectException('InvalidArgumentException');
        $client->clickLink('Изменить');

        $client->request('GET', '/courses/new/');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/new');
        self::assertResponseRedirects('/login');

        // Авторизован как обычный пользователь
        $client->followRedirects();
        $crawler = $client->request('GET', '/courses');
        $this->authorize($client, AbstractTest::USER_EMAIL, AbstractTest::USER_PASSWORD);

        $this->expectException('InvalidArgumentException');
        $client->clickLink('Добавить курс');

        $client->request('GET', '/courses/new/');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/new');
        $this->assertResponseCode(403);
    }

    /**
     * @depends testShowCourse
     */
    public function testEditCourse(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $courseRepository = self::getEntityManager()->getRepository(Course::class);

        $client->request('GET', '/');
        // Вход
        $this->authorize($client, AbstractTest::ADMIN_EMAIL, AbstractTest::ADMIN_PASSWORD);

        $client->request('GET', '/courses/1');
        $crawler = $client->clickLink('Изменить');

        // Кнопка обновления не привязана к форме, поэтому получим форму по-другому
        $form = $crawler->filter('form')->first()->form();

        // Проверка заполненненных полей
        $values = $form->getValues();
        $courseId = 1;
        $course = $courseRepository->find($courseId);
        self::assertEquals($course->getCode(), $values['course[code]']);
        self::assertEquals($course->getName(), $values['course[name]']);
        self::assertEquals($course->getDescription(), $values['course[description]']);

        $code = 'test';
        $name = 'Тестовый курс';
        $description = 'Описание тестового курса';

        // Сохранение обновленного курса
        $form['course[code]'] = $code;
        $form['course[name]'] = $name;
        $form['course[description]'] = $description;
        $client->submit($form);

        $this->assertResponseOk();
        self::assertRouteSame('app_course_index');

        // Проверка сохраненного курса
        $course = $courseRepository->find($courseId);
        self::assertEquals($code, $course->getCode());
        self::assertEquals($name, $course->getName());
        self::assertEquals($description, $course->getDescription());
    }

    public function testEditCourseFailed(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        // Неавторизован
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $client->clickLink('Изменить');

        $crawler = $client->request('GET', '/courses/1/edit/');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/1/edit');
        self::assertResponseRedirects('/login');

        // Авторизован как обычный пользователь
        $crawler = $client->request('GET', '/');
        $this->authorize($client, AbstractTest::USER_EMAIL, AbstractTest::USER_PASSWORD);
        $crawler = $client->request('GET', '/courses/1');

        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $client->clickLink('Изменить');

        $client->request('GET', '/courses/1/edit/');
        $this->assertResponseCode(403);

        $client->request('POST', '/courses/1/edit');
        $this->assertResponseCode(403);
    }

    /**
     * @depends testShowCourse
     */
    public function testDeleteCourse(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        $courseRepository = self::getEntityManager()->getRepository(Course::class);

        $client->request('GET', '/');
        // Вход
        $this->authorize($client, AbstractTest::ADMIN_EMAIL, AbstractTest::ADMIN_PASSWORD);
        $crawler = $client->request('GET', '/courses/1');
        $client->submitForm('Удалить');

        $this->assertResponseOk();
        self::assertRouteSame('app_course_index');

        self::assertNull($courseRepository->find(1));
    }

    public function testDeleteCourseFailed(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $this->mockBillingClient($client);

        // Неавторизован
        $crawler = $client->request('GET', '/courses/');
        $this->assertResponseOk();

        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $client->submitForm('Удалить');

        $crawler = $client->request('POST', '/courses/1/delete/');
        $this->assertResponseCode(403);

        // Авторизован как обычный пользователь
        $this->authorize($client, AbstractTest::USER_EMAIL, AbstractTest::USER_PASSWORD);

        // Без прав админа нет кнопки
        $this->expectException('InvalidArgumentException');
        $client->submitForm('Удалить');

        $client->request('POST', '/courses/1/delete');
        $this->assertResponseCode(403);
    }
}
