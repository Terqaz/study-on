<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Tests\AbstractTest;

class CourseControllerTest extends AbstractTest
{
    protected const CREATE_COURSE_BUTTON_TEXT = 'Создать курс';
    protected const ADD_COURSE_BUTTON_TEXT = 'Добавить курс';

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

        $crawler = $client->request('GET', '/courses/1');
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

    public function testGetNewCourseForm(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $client->request('GET', '/courses/');  //app_course_new
        $crawler = $client->clickLink(self::ADD_COURSE_BUTTON_TEXT);
        $this->assertResponseOk();

        $form = $crawler
            ->selectButton(self::CREATE_COURSE_BUTTON_TEXT)
            ->form();

        // Проверка присутствия полей
        self::assertFalse($form->has('course[id]'));
        self::assertTrue($form->has('course[code]'));
        self::assertTrue($form->has('course[name]'));
        self::assertTrue($form->has('course[description]'));
    }

    /**
     * @depends testGetNewCourseForm
     */
    public function testSubmitNewCourse(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $oldCount = $courseRepository->count([]);

        $client->request('GET', '/courses');
        $client->clickLink(self::ADD_COURSE_BUTTON_TEXT);

        // Если не указан код
        $crawler = $client->submitForm(self::CREATE_COURSE_BUTTON_TEXT, [
            'course[name]' => 'Тестовый курс',
            'course[description]' => 'Описание тестового курса'
        ]);
        $this->assertResponseCode(500);

        // Если не указано имя
        $client->back();
        $crawler = $client->submitForm(self::CREATE_COURSE_BUTTON_TEXT, [
            'course[code]' => 'test',
            'course[description]' => 'Описание тестового курса'
        ]);
        $this->assertResponseCode(500);

        // Если не указано описание
        $client->back();
        $crawler = $client->submitForm(self::CREATE_COURSE_BUTTON_TEXT, [
            'course[code]' => 'test-1',
            'course[name]' => 'Тестовый курс1'
        ]);
        $this->assertResponseOk();  // нет ошибки
        self::assertRouteSame('app_course_index');

        // Указано всё
        $client->back();
        $client->submitForm(self::CREATE_COURSE_BUTTON_TEXT, [
            'course[code]' => 'test-2',
            'course[name]' => 'Тестовый курс 2',
            'course[description]' => 'Описание тестового курса 2'
        ]);
        $this->assertResponseOk();

        // Создание курса с тем же кодом невозможно
        $client->back();
        $client->submitForm(self::CREATE_COURSE_BUTTON_TEXT, [
            'course[code]' => 'test-2',
            'course[name]' => 'Тестовый курс 3',
            'course[description]' => 'Описание тестового курса 3'
        ]);
        $this->assertResponseCode(500);

        // В итоге добавилось 2 курса
        self::assertEquals($oldCount + 2, $courseRepository->count([]));
    }

    /**
     * @depends testShowCourse
     */
    public function testEditCourse(): void
    {
        $client = static::getClient();
        $client->followRedirects();
        $courseRepository = self::getEntityManager()->getRepository(Course::class);

        $client->request('GET', '/courses/1');
        $this->assertResponseOk();
        $crawler = $client->clickLink(self::CHANGE_BUTTON_TEXT);

        // Кнопка обновления не привязана к форме, поэтому получим её по-другому
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
        $client->clickLink(self::UPDATE_BUTTON_TEXT);

        $this->assertResponseOk();
        self::assertRouteSame('app_course_index');

        // Проверка сохраненного курса
        $course = $courseRepository->find($courseId);
        self::assertEquals($code, $course->getCode());
        self::assertEquals($name, $course->getName());
        self::assertEquals($description, $course->getDescription());
    }

    /**
     * @depends testShowCourse
     */
    public function testDeleteCourse(): void
    {
        $client = static::getClient();
        $client->followRedirects();
        $courseRepository = self::getEntityManager()->getRepository(Course::class);

        $client->request('GET', '/courses/1');
        $client->submitForm('Удалить');
        $this->assertResponseOk();
        self::assertRouteSame('app_course_index');

        self::assertNull($courseRepository->find(1));
    }
}

// 1. Проверить для всех GET/POST экшенов контроллеров, что возвращается корректный http-статус
// 2. В GET-методах проверить, что возвращается то, что ожидается (например, список курсов в нужном количестве, страница
//  курса с правильным количеством уроков и т.д.). Также проверить, что при обращении по несуществующему URL курса/урока
//  и так далее отдается 404
// 3. Проверить работу форм создания, редактирования и удаления сущностей. Убедиться, что выполняются необходимые
//  валидации и выдаются соответствующие сообщения об ошибках в формах. Также убедиться, что происходит корректная
//  обработка формы при валидных данных.
// 4. Проверку стоит осуществлять с точки зрения пользователя. То есть вызывать отправку формы не на конкретный URL, а,
//  например, сначала в тесте перейти на страницу курса, там нажать Добавить урок, заполнить форму данными и отправить.
//  После отправки формы добавления урока проверить, что произошел редирект на страницу курса и на ней стало на один
//  урок больше.
