<?php

namespace App\Controller;

use App\Entity\Lesson;
use App\Form\LessonType;
use App\Repository\CourseRepository;
use App\Repository\LessonRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/lessons")
 * @IsGranted("ROLE_USER")
 */
class LessonController extends AbstractController
{
//    /**
//     * @Route("/", name="app_lesson_index", methods={})
//     */
//    public function index(LessonRepository $lessonRepository): Response
//    {
//        return $this->render('lesson/index.html.twig', [
//            'lessons' => $lessonRepository->findAll(),
//        ]);
//    }

    /**
     * @Route("/new", name="app_lesson_new", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function new(Request $request, LessonRepository $lessonRepository, CourseRepository $courseRepository): Response
    {
        $courseId = (int)$request->query->get('course_id');
        $course = $courseRepository->find($courseId);

        $lesson = new Lesson();
        $lesson->setCourse($course);

        $form = $this->createForm(
            LessonType::class,
            $lesson,
            ['course_id' => $courseId]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lessonRepository->add($lesson);

            return $this->redirectToRoute(
                'app_course_show',
                ['id' => $lesson->getCourse()->getId()],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->renderForm('lesson/new.html.twig', [
            'lesson' => $lesson,
            'form' => $form,
            'course' => $course
        ]);
    }

    /**
     * @Route("/{id}", name="app_lesson_show", methods={"GET"})
     */
    public function show(Lesson $lesson): Response
    {
        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="app_lesson_edit", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function edit(Request $request, Lesson $lesson, LessonRepository $lessonRepository): Response
    {
        $form = $this->createForm(
            LessonType::class,
            $lesson,
            ['course_id' => (int)$lesson->getCourse()->getId()]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lessonRepository->add($lesson);

            return $this->redirectToRoute(
                'app_lesson_show',
                ['id' => $lesson->getId()],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->renderForm('lesson/edit.html.twig', [
            'lesson' => $lesson,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_lesson_delete", methods={"POST"})
     * @IsGranted("ROLE_SUPER_ADMIN")
     */
    public function delete(Request $request, Lesson $lesson, LessonRepository $lessonRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$lesson->getId(), $request->request->get('_token'))) {
            $lessonRepository->remove($lesson);
        }

        return $this->redirectToRoute(
            'app_course_show',
            ['id' => $lesson->getCourse()->getId()],
            Response::HTTP_SEE_OTHER
        );
    }
}
