<?php
/*************************************************************************************/
/*      This file is part of the RewriteUrl module for Thelia.                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace RewriteUrl\Controller\Admin;

use Thelia\Model\RewritingUrl as ModuleRewritingUrl;
use RewriteUrl\Event\RewriteUrlEvent;
use RewriteUrl\Event\RewriteUrlEvents;
use RewriteUrl\Form\AddUrlForm;
use RewriteUrl\Form\ReassignForm;
use RewriteUrl\Form\SetDefaultForm;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\CategoryI18nQuery;
use Thelia\Model\ContentI18nQuery;
use Thelia\Model\FolderI18nQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;
use Thelia\Log\Tlog;

/**
 * Class RewriteUrlAdminController
 * @package RewriteUrl\Controller\Admin
 * @author Vincent Lopes <vlopes@openstudio.fr>
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class RewriteUrlAdminController extends BaseAdminController
{
    /** @var array */
    private $correspondence = array(
        'brand'     => 'brand',
        'category'  => 'categories',
        'content'   => 'content',
        'folder'    => 'folders',
        'product'   => 'products'
    );

    /**
     * @return mixed
     */
    public function deleteAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'RewriteUrl', AccessManager::DELETE)) {
            return $response;
        }

        $id_url = $this->getRequest()->request->get('id_url');
        $rewritingUrl = RewritingUrlQuery::create()->findOneById($id_url);

        if ($rewritingUrl) {
            $event = new RewriteUrlEvent($rewritingUrl);
            $this->dispatch(RewriteUrlEvents::REWRITEURL_DELETE, $event);
        }

        if (method_exists($this, 'generateSuccessRedirect')) {
            //for 2.1
            return $this->generateRedirectFromRoute(
                'admin.'.$this->correspondence[$rewritingUrl->getView()].'.update',
                [
                    $rewritingUrl->getView().'_id'=>$rewritingUrl->getViewId(),
                    'current_tab' => 'modules'
                ],
                [
                    $rewritingUrl->getView().'_id'=>$rewritingUrl->getViewId()
                ]
            );
        } else {
            //for 2.0
            $this->redirectToRoute(
                'admin.'.$this->correspondence[$rewritingUrl->getView()].'.update',
                [
                    $rewritingUrl->getView().'_id'=>$rewritingUrl->getViewId(),
                    'current_tab' => 'modules'
                ],
                [
                    $rewritingUrl->getView().'_id'=>$rewritingUrl->getViewId()
                ]
            );
        }
    }

    /**
     * @return mixed
     */
    public function addAction()
    {
        $message = false;

        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'RewriteUrl', AccessManager::CREATE)) {
            return $response;
        }

        $addForm = new AddUrlForm($this->getRequest());

        try {
            $form = $this->validateForm($addForm);
            $data = $form->getData($form);

            if (RewritingUrlQuery::create()->findOneByUrl(($data['url'])) !== null) {
                throw new \Exception("Url already exist");
            }

            $rewriting = new ModuleRewritingUrl();
            $rewriting->setUrl($data['url']);
            $rewriting->setView($data['view']);
            $rewriting->setViewId($data['view-id']);
            $rewriting->setViewLocale($data['locale']);

            $rewriteDefault = RewritingUrlQuery::create()
                ->filterByView($rewriting->getView())
                ->filterByViewId($rewriting->getViewId())
                ->filterByViewLocale($rewriting->getViewLocale())
                ->findOneByRedirected(null);

            if ($data['default'] == 1) {
                $rewriting->setRedirected(null);
            } else {
                if ($rewriteDefault !== null) {
                    $rewriting->setRedirected($rewriteDefault->getId());
                } else {
                    $rewriting->setRedirected(null);
                }
            }

            $event = new RewriteUrlEvent($rewriting);
            $this->dispatch(RewriteUrlEvents::REWRITEURL_ADD, $event);

            if (method_exists($this, 'generateSuccessRedirect')) {
                //for 2.1
                return $this->generateSuccessRedirect($addForm);
            } else {
                //for 2.0
                $this->redirectSuccess($addForm);
            }

        } catch (FormValidationException $e) {
            $message = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        if ($message !== false) {
            Tlog::getInstance()->error(sprintf("Error during order delivery process : %s. Exception was %s", $message, $e->getMessage()));

            $addForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($addForm)
                ->setGeneralError($message)
            ;
        }

        if (method_exists($this, 'generateSuccessRedirect')) {
            //for 2.1
            return $this->generateSuccessRedirect($addForm);
        } else {
            //for 2.0
            $this->redirectSuccess($addForm);
        }
    }

    /**
     * @return mixed
     */
    public function setDefaultAction()
    {
        $message = false;

        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'RewriteUrl', AccessManager::UPDATE)) {
            return $response;
        }

        $setDefaultForm = new SetDefaultForm($this->getRequest());

        try {
            $form = $this->validateForm($setDefaultForm);
            $data = $form->getData($form);

            $rewritingUrl = RewritingUrlQuery::create()->findOneById($data['rewrite-id']);
            $newEvent = new RewriteUrlEvent($rewritingUrl);
            $this->dispatch(RewriteUrlEvents::REWRITEURL_SET_DEFAULT, $newEvent);
        } catch (FormValidationException $e) {
            $message = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        if ($message !== false) {
            $setDefaultForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($setDefaultForm)
                ->setGeneralError($message)
            ;
        }

        if (method_exists($this, 'generateSuccessRedirect')) {
            //for 2.1
            return $this->generateSuccessRedirect($setDefaultForm);
        } else {
            //for 2.0
            $this->redirectSuccess($setDefaultForm);
        }
    }

    /**
     * @return mixed
     */
    public function reassignAction()
    {
        $message = false;
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'RewriteUrl', AccessManager::UPDATE)) {
            return $response;
        }

        $reassignForm = new ReassignForm($this->getRequest());

        try {
            $form = $this->validateForm($reassignForm);
            $data = $form->getData($form);

            $newRewrite = explode('::', $data['select-reassign']);
            $newView = $newRewrite[1];
            $newViewId = $newRewrite[0];

            $rewrite = RewritingUrlQuery::create()->findOneById($data['rewrite-id']);

            $isRedirection = RewritingUrlQuery::create()->findByRedirected($rewrite->getId());

            //Update urls who redirected to updated URL
            if ($isRedirection != null) {
                /** @var \Thelia\Model\RewritingUrl $redirected */
                foreach ($isRedirection as $redirected) {
                    $redirected->setRedirected($rewrite->getRedirected());
                    $newEvent = new RewriteUrlEvent($redirected);
                    $this->dispatch(RewriteUrlEvents::REWRITEURL_UPDATE, $newEvent);
                }
            }

            $rewrite->setView($newView)
                ->setViewId($newViewId);

            //Check if default url already exist for the view with the locale
            $rewriteDefault = RewritingUrlQuery::create()
                ->filterByView($newView)
                ->filterByViewId($newViewId)
                ->filterByViewLocale($rewrite->getViewLocale())
                ->findOneByRedirected(null);

            if ($rewriteDefault !== null) {
                $rewrite->setRedirected($rewriteDefault->getId());
            } else {
                $rewrite->setRedirected(null);
            }

            $event = new RewriteUrlEvent($rewrite);
            $this->dispatch(RewriteUrlEvents::REWRITEURL_UPDATE, $event);

            if (method_exists($this, 'generateSuccessRedirect')) {
                //for 2.1
                return $this->generateSuccessRedirect($reassignForm);
            } else {
                //for 2.0
                $this->redirectSuccess($reassignForm);
            }
        } catch (FormValidationException $e) {
            $message = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        if ($message !== false) {
            $reassignForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($reassignForm)
                ->setGeneralError($message)
            ;
        }
    }

    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function existAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'RewriteUrl', AccessManager::VIEW)) {
            return $response;
        }

        $search = $this->getRequest()->query->get('q');

        $rewritingUrl = RewritingUrlQuery::create()->findOneByUrl($search);

        if ($rewritingUrl !== null) {
            $route = $this->getRoute(
                "admin.".$this->correspondence[$rewritingUrl->getView()].".update",
                [$rewritingUrl->getView().'_id'=>$rewritingUrl->getViewId()]
            );
            $url = URL::getInstance()->absoluteUrl(
                $route,
                [$rewritingUrl->getView().'_id'=>$rewritingUrl->getViewId()]
            );

            $rewritingUrlArray = ["reassignUrl" => $url];

            return $this->jsonResponse(json_encode($rewritingUrlArray));
        } else {
            return $this->jsonResponse(json_encode(false));
        }
    }

    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function searchAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'RewriteUrl', AccessManager::VIEW)) {
            return $response;
        }

        $search = '%'.$this->getRequest()->query->get('q').'%';

        $resultArray = array();

        $categoriesI18n = CategoryI18nQuery::create()->filterByTitle($search)->limit(10);
        $contentsI18n = ContentI18nQuery::create()->filterByTitle($search)->limit(10);
        $foldersI18n = FolderI18nQuery::create()->filterByTitle($search)->limit(10);
        $brandsI18n = BrandI18nQuery::create()->filterByTitle($search)->limit(10);

        $productsI18n = ProductI18nQuery::create()->filterByTitle($search)->limit(10);
        $productsRef = ProductQuery::create()->filterByRef($search)->limit(10);

        /** @var \Thelia\Model\CategoryI18n $categoryI18n */
        foreach ($categoriesI18n as $categoryI18n) {
            $category = $categoryI18n->getCategory();
            $resultArray['category'][$category->getId()] = $categoryI18n->getTitle();
        }

        /** @var \Thelia\Model\ContentI18n $contentI18n */
        foreach ($contentsI18n as $contentI18n) {
            $content = $contentI18n->getContent();
            $resultArray['content'][$content->getId()] = $contentI18n->getTitle();
        }

        /** @var \Thelia\Model\FolderI18n $folderI18n */
        foreach ($foldersI18n as $folderI18n) {
            $folder = $folderI18n->getFolder();
            $resultArray['folder'][$folder->getId()] = $folderI18n->getTitle();
        }

        /** @var \Thelia\Model\BrandI18n $brandI18n */
        foreach ($brandsI18n as $brandI18n) {
            $brand = $brandI18n->getBrand();
            $resultArray['brand'][$brand->getId()] = $brandI18n->getTitle();
        }

        /** @var \Thelia\Model\ProductI18n $productI18n */
        foreach ($productsI18n as $productI18n) {
            $product = $productI18n->getProduct();
            $resultArray['product'][$product->getId()] = $productI18n->getTitle();
        }

        /** @var \Thelia\Model\Product $product */
        foreach ($productsRef as $product) {
            $productI18n = ProductI18nQuery::create()->filterByProduct($product)->findOne();
            $resultArray['product'][$product->getId()] = $productI18n->getTitle();
        }

        return $this->jsonResponse(json_encode($resultArray));
    }
}
