<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    const COURCES_DATA = [
            [
                'code' => 'python-programming',
                'name' => 'Программирование на Python',
                'description' => 'Курс посвящен базовым понятиям и элементам языка программирования Python (операторы, числовые и строковые переменные, списки, условия и циклы). Курс является вводным и наиболее подойдет слушателям, не имеющим опыта написания программ ни на одном из языков программирования.'
            ], [
                'code' => 'interactive-sql-trainer',
                'name' => 'Интерактивный тренажер по SQL',
                'description' => 'В курсе большинство шагов — это практические задания на создание SQL-запросов. Каждый шаг включает  минимальные теоретические аспекты по базам данных или языку SQL, примеры похожих запросов и пояснение к реализации.'
            ], [
                'code' => 'building-information-modeling',
                'name' => 'Информационное моделирование зданий',
                'description' => 'Курс посвящён изучению технологии информационного моделирования зданий на примере программы Autodesk Revit Architecture'
            ]
        ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::COURCES_DATA as $i => $iValue) {
            $course = (new Course())
                ->setId($i+1) // 23..25
                ->setCode($iValue['code'])
                ->setName($iValue['name'])
                ->setDescription($iValue['description']);

            $manager->persist($course);
            // Нужно убрать автогенерацию айди в рантайме или как-то по другому связывать внешние ключи
            // пока что не знаю как
            // Enforce specified record ID
//            $metadata = $manager->getClassMetaData(get_class($course));
//            $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);
        }

        $manager->flush();
    }
}
