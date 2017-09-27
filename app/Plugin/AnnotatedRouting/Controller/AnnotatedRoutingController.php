<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2017 LOCKON CO.,LTD. All Rights Reserved.
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

namespace Plugin\AnnotatedRouting\Controller;
use Eccube\Annotation\Component;
use Eccube\Application;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Component
 * @Route(value="/arc", service=AnnotatedRoutingController::class)
 */
class AnnotatedRoutingController
{
    /**
     * @Route("/")
     * @Template("AnnotatedRouting/Resource/template/index.twig")
     */
    public function index(Application $app)
    {
        return [];
    }

    /**
     * @Route("/form")
     * @Method("GET")
     * @Template("AnnotatedRouting/Resource/template/form.twig")
     */
    public function form(Application $app)
    {
        return [];
    }

    /**
     * @Route("/form")
     * @Method("POST")
     */
    public function submit(Application $app, Request $request)
    {
        return $app->escape('Hello, '.$request->get('value'));
    }
}