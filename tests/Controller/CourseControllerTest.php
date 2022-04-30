<?php

namespace App\Tests\Controller;

use App\Tests\AbstractTest;

class CourseControllerTest extends AbstractTest
{
    protected const CREATE_COURSE_BUTTON_TEXT = 'Создать курс';

    public function testRedirectToCourses(): void
    {
        $client = static::getClient();
        $client->request('GET', '/');
        $this->assertResponseRedirect();

        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        self::assertRouteSame('app_course_index');
    }

    public function testGetCourse(): void
    {
        $client = static::getClient();
        $client->request('GET', '/courses/34636643');
        $this->assertResponseCode(404);

        $client->request('GET', '/courses/sdsb');
        $this->assertResponseCode(500);

        $crawler = $client->request('GET', '/courses/1');
        $this->assertResponseOk();
        self::assertEquals(3, $crawler->filter('ol li')->count());
    }

//    public function testGetCoursesIndex(): void //TODO
//    {
//        $this->setUp();
//        $client = static::getClient();
//        $crawler = $client->request('GET', '/courses');
//
//        self::assertEquals(3, $crawler->filter(".card")->count());
//        $this->tearDown();
//    }

    public function testGetNewCourseForm(): void
    {
        $client = static::getClient();

        $crawler = $client->request('GET', '/courses/new');  //app_course_new
        $this->assertResponseOk();

        $form = $crawler
            ->selectButton(self::CREATE_COURSE_BUTTON_TEXT)
            ->form();

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

        // Форма с ошибкой
        $client->request('GET', '/courses/new');  //app_course_new
        $crawler = $client->submitForm(self::CREATE_COURSE_BUTTON_TEXT, [
            'course[code]' => 'test',
//            'course[name]' => 'Тестовый курс',
            'course[description]' => 'Описание тестового курса'
        ]);
        $this->assertResponseCode(500);

        // Правильная форма
        $client->request('GET', '/courses/new');  //app_course_new
        $client->submitForm(self::CREATE_COURSE_BUTTON_TEXT, [
            'course[code]' => 'test',
            'course[name]' => 'Тестовый курс',
            'course[description]' => 'Описание тестового курса'
        ]);
        $this->assertResponseRedirect();
        $client->followRedirect();
        $this->assertResponseOk();
    }
}

//        $client->request('GET', '/courses/1');  //app_course_show
//        $this->assertResponseOk();
//
//        $client->request('GET', '/courses/1/edit');  //app_course_edit
//        $this->assertResponseOk();
//
//        $client->request('POST', '/courses/1/edit');  //app_course_edit
//        $this->assertResponseOk();
//
//        $client->request('POST', '/courses/1');  //app_course_delete
//        $this->assertResponseOk();
//
//        $client->request('GET', '/lessons/new');  //app_lesson_new
//        $this->assertResponseOk();
//
//        $client->request('POST', '/lessons/new');  //app_lesson_new
//        $this->assertResponseOk();
//
//        $client->request('GET', '/lessons/1');  //app_lesson_show
//        $this->assertResponseOk();
//
//        $client->request('GET', '/lessons/1/edit');  //app_lesson_edit
//        $this->assertResponseOk();
//
//        $client->request('POST', '/lessons/1/edit');  //app_lesson_edit
//        $this->assertResponseOk();
//
//        $client->request('POST', '/lessons/1');  //app_lesson_delete
//        $this->assertResponseOk();

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
