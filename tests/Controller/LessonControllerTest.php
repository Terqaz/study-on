<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Tests\AbstractTest;

class LessonControllerTest extends AbstractTest
{
    protected const ADD_LESSON_BUTTON_TEXT = 'Добавить урок';
    protected const CREATE_LESSON_BUTTON_TEXT = 'Создать урок';
    protected const CHANGE_LESSON_BUTTON_TEXT = 'Изменить урок';

    public function testShowLesson(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $client->request('GET', '/courses/1');
        $crawler = $client->clickLink('Операторы. Переменные. Типы данных. Условия');
        $this->assertResponseOk();

        // Проверка заголовка
        self::assertEquals(
            'Операторы. Переменные. Типы данных. Условия',
            $crawler->filter('h1')->text()
        );

        // Если несуществующий айдишник
        $client->request('GET', '/lessons/34636643');
        $this->assertResponseCode(404);

        // Если вместо айдишника буквы
        $client->request('GET', '/lessons/sdsb');
        $this->assertResponseCode(500);
    }

    /**
     * @depends testShowLesson
     */
    public function testGetNewLessonForm(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $course = $courseRepository->find(1);

        $client->request('GET', '/courses/1');  //app_course_new
        $crawler = $client->clickLink(self::ADD_LESSON_BUTTON_TEXT);
        $this->assertResponseOk();

        $form = $crawler
            ->selectButton(self::CREATE_LESSON_BUTTON_TEXT)
            ->form();

        // Проверка присутствия полей
        self::assertFalse($form->has('lesson[id]'));
        self::assertEquals($course->getId(), (int)$form['lesson[course_id]']->getValue());
        self::assertTrue($form->has('lesson[name]'));
        self::assertTrue($form->has('lesson[content]'));
        self::assertTrue($form->has('lesson[serialNumber]'));
    }

    /**
     * @depends testGetNewLessonForm
     */
    public function testSubmitNewLesson(): void
    {
        $client = static::getClient();
        $client->followRedirects();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $course = $courseRepository->find(1);
        $oldCount = $course->getLessons()->count();

        $client->request('GET', '/courses/1');
        $client->clickLink(self::ADD_LESSON_BUTTON_TEXT);

        // Если не указано имя
        $client->submitForm(self::CREATE_LESSON_BUTTON_TEXT, [
            'lesson[content]' => 'Тестовое описание урока',
            'lesson[serialNumber]' => '4'
        ]);
        $this->assertResponseCode(500);

        // Если не указано описание
        $client->back();
        $crawler = $client->submitForm(self::CREATE_LESSON_BUTTON_TEXT, [
            'lesson[name]' => 'Тестовый урок',
            'lesson[serialNumber]' => '4'
        ]);
        $this->assertResponseCode(500);

        // Все указано правильно и без серийного номера
        $client->back();
        $client->submitForm(self::CREATE_LESSON_BUTTON_TEXT, [
            'lesson[name]' => 'Тестовый урок',
            'lesson[content]' => 'Тестовое описание урока',
        ]);
        $this->assertResponseOk();
        self::assertRouteSame('app_course_show');

        // Все указано правильно
        $client->back();
        $client->submitForm(self::CREATE_LESSON_BUTTON_TEXT, [
            'lesson[name]' => 'Тестовый урок',
            'lesson[content]' => 'Тестовое описание урока',
            'lesson[serialNumber]' => '1'
        ]);
        $this->assertResponseOk();

        // В итоге добавилось 2 урока
        self::assertEquals($oldCount + 2, $courseRepository->find(1)->getLessons()->count());
    }

    /**
     * @depends testShowLesson
     */
    public function testEditLesson(): void
    {
        $client = static::getClient();
        $client->followRedirects();
        $lessonRepository = self::getEntityManager()->getRepository(Lesson::class);

        $client->request('GET', '/lessons/1');

        $crawler = $client->clickLink(self::CHANGE_LESSON_BUTTON_TEXT);

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
        $client->clickLink(self::UPDATE_BUTTON_TEXT);
        $this->assertResponseOk();
        self::assertRouteSame('app_lesson_show');

        // Проверка сохраненного курса
        $lesson = $lessonRepository->find($lessonId);
        self::assertEquals($courseId, $lesson->getCourse()->getId());
        self::assertEquals($name, $lesson->getName());
        self::assertEquals($content, $lesson->getContent());
        self::assertEquals($serialNumber, $lesson->getSerialNumber());
    }

    /**
     * @depends testShowLesson
     */
    public function testDeleteLesson(): void
    {
        $client = static::getClient();
        $client->followRedirects();
        $lessonRepository = self::getEntityManager()->getRepository(Lesson::class);

        $client->request('GET', '/lessons/1');
        $client->submitForm(self::DELETE_BUTTON_TEXT);
        $this->assertResponseOk();

        self::assertRouteSame('app_course_show');

        self::assertNull($lessonRepository->find(1));
    }
}

//
//        $client->request('POST', '/lessons/1');  //app_lesson_delete
//        $this->assertResponseOk();