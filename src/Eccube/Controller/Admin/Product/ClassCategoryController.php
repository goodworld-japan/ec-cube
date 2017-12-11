<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Eccube\Controller\Admin\Product;

use Doctrine\ORM\EntityManager;
use Eccube\Annotation\Inject;
use Eccube\Application;
use Eccube\Controller\AbstractController;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Admin\ClassCategoryType;
use Eccube\Repository\ClassCategoryRepository;
use Eccube\Repository\ClassNameRepository;
use Eccube\Repository\ProductClassRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route(service=ClassCategoryController::class)
 */
class ClassCategoryController extends AbstractController
{
    /**
     * @Inject("orm.em")
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @Inject(ProductClassRepository::class)
     * @var ProductClassRepository
     */
    protected $productClassRepository;

    /**
     * @Inject("eccube.event.dispatcher")
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @Inject("form.factory")
     * @var FormFactory
     */
    protected $formFactory;

    /**
     * @Inject(ClassCategoryRepository::class)
     * @var ClassCategoryRepository
     */
    protected $classCategoryRepository;

    /**
     * @Inject(ClassNameRepository::class)
     * @var ClassNameRepository
     */
    protected $classNameRepository;

    /**
     * @Route("/%admin_route%/product/class_category/{class_name_id}", requirements={"class_name_id" = "\d+"}, name="admin_product_class_category")
     * @Route("/%admin_route%/product/class_category/{class_name_id}/{id}/edit", requirements={"class_name_id" = "\d+", "id" = "\d+"}, name="admin_product_class_category_edit")
     * @Template("Product/class_category.twig")
     */
    public function index(Application $app, Request $request, $class_name_id, $id = null)
    {
        //
        $ClassName = $this->classNameRepository->find($class_name_id);
        if (!$ClassName) {
            throw new NotFoundHttpException('商品規格が存在しません');
        }
        if ($id) {
            $TargetClassCategory = $this->classCategoryRepository->find($id);
            if (!$TargetClassCategory || $TargetClassCategory->getClassName() != $ClassName) {
                throw new NotFoundHttpException('商品規格が存在しません');
            }
        } else {
            $TargetClassCategory = new \Eccube\Entity\ClassCategory();
            $TargetClassCategory->setClassName($ClassName);
        }

        //
        $builder = $this->formFactory
            ->createBuilder(ClassCategoryType::class, $TargetClassCategory);

        $event = new EventArgs(
            array(
                'builder' => $builder,
                'ClassName' => $ClassName,
                'TargetClassCategory' => $TargetClassCategory,
            ),
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_CLASS_CATEGORY_INDEX_INITIALIZE, $event);

        $form = $builder->getForm();

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);
            if ($form->isValid()) {
                log_info('規格分類登録開始', array($id));

                $this->classCategoryRepository->save($TargetClassCategory);

                log_info('規格分類登録完了', array($id));

                $event = new EventArgs(
                    array(
                        'form' => $form,
                        'ClassName' => $ClassName,
                        'TargetClassCategory' => $TargetClassCategory,
                    ),
                    $request
                );
                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_CLASS_CATEGORY_INDEX_COMPLETE, $event);

                $app->addSuccess('admin.class_category.save.complete', 'admin');

                return $app->redirect($app->url('admin_product_class_category', array('class_name_id' => $ClassName->getId())));
            }
        }

        $ClassCategories = $this->classCategoryRepository->getList($ClassName);

        return [
            'form' => $form->createView(),
            'ClassName' => $ClassName,
            'ClassCategories' => $ClassCategories,
            'TargetClassCategory' => $TargetClassCategory,
        ];
    }

    /**
     * @Method("DELETE")
     * @Route("/%admin_route%/product/class_category/{class_name_id}/{id}/delete", requirements={"class_name_id" = "\d+", "id" = "\d+"}, name="admin_product_class_category_delete")
     */
    public function delete(Application $app, Request $request, $class_name_id, $id)
    {
        $this->isTokenValid($app);

        $ClassName = $this->classNameRepository->find($class_name_id);
        if (!$ClassName) {
            throw new NotFoundHttpException('商品規格が存在しません');
        }

        log_info('規格分類削除開始', array($id));

        $TargetClassCategory = $this->classCategoryRepository->find($id);
        if (!$TargetClassCategory || $TargetClassCategory->getClassName() != $ClassName) {
            $app->deleteMessage();
            return $app->redirect($app->url('admin_product_class_category', array('class_name_id' => $ClassName->getId())));
        }

        $num = $this->productClassRepository->createQueryBuilder('pc')
            ->select('count(pc.id)')
            ->where('pc.ClassCategory1 = :id OR pc.ClassCategory2 = :id')
            ->setParameter('id',$id)
            ->getQuery()
            ->getSingleScalarResult();
        if ($num > 0) {
            $app->addError('admin.class_category.delete.hasproduct', 'admin');
        } else {
            try {
                $this->classCategoryRepository->delete($TargetClassCategory);

                $event = new EventArgs(
                    array(
                        'ClassName' => $ClassName,
                        'TargetClassCategory' => $TargetClassCategory,
                    ),
                    $request
                );
                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_CLASS_CATEGORY_DELETE_COMPLETE, $event);

                $app->addSuccess('admin.class_category.delete.complete', 'admin');

                log_info('規格分類削除完了', array($id));

            } catch (\Exception $e) {
                log_error('規格分類削除エラー', array($id, $e));

                $message = $app->trans('admin.delete.failed.foreign_key', ['%name%' => '規格分類']);
                $app->addError($message, 'admin');            }
        }

        return $app->redirect($app->url('admin_product_class_category', array('class_name_id' => $ClassName->getId())));
    }

    /**
     * @Method("PUT")
     * @Route("/%admin_route%/product/class_category/{class_name_id}/{id}/visibility", requirements={"class_name_id" = "\d+", "id" = "\d+"}, name="admin_product_class_category_visibility")
     */
    public function visibility(Application $app, Request $request, $class_name_id, $id)
    {
        $this->isTokenValid($app);

        $ClassName = $this->classNameRepository->find($class_name_id);
        if (!$ClassName) {
            throw new NotFoundHttpException('商品規格が存在しません');
        }

        log_info('規格分類表示変更開始', array($id));

        $TargetClassCategory = $this->classCategoryRepository->find($id);
        if (!$TargetClassCategory || $TargetClassCategory->getClassName() != $ClassName) {
            $app->deleteMessage();
            return $app->redirect($app->url('admin_product_class_category', array('class_name_id' => $ClassName->getId())));
        }

        $this->classCategoryRepository->toggleVisibility($TargetClassCategory);

        log_info('規格分類表示変更完了', array($id));

        $event = new EventArgs(
            array(
                'ClassName' => $ClassName,
                'TargetClassCategory' => $TargetClassCategory,
            ),
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_CLASS_CATEGORY_DELETE_COMPLETE, $event);

        $app->addSuccess('admin.class_category.delete.complete', 'admin');

        return $app->redirect($app->url('admin_product_class_category', array('class_name_id' => $ClassName->getId())));
    }

    /**
     * @Method("POST")
     * @Route("/product/class_category/sort_no/move", name="admin_product_class_category_sort_no_move")
     */
    public function moveSortNo(Application $app, Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $sortNos = $request->request->all();
            foreach ($sortNos as $categoryId => $sortNo) {
                $ClassCategory = $this->classCategoryRepository
                    ->find($categoryId);
                $ClassCategory->setSortNo($sortNo);
                $this->entityManager->persist($ClassCategory);
            }
            $this->entityManager->flush();
        }
        return true;
    }
}
