<?php

namespace Application\AnunciosBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Application\AnunciosBundle\Entity\Post;
use Application\UserBundle\Entity\Contact;
use Application\AnunciosBundle\Form\PostType;
use Application\UserBundle\Form\ContactType;
//use Symfony\Component\HttpFoundation\Request;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\View\DefaultView;
use Pagerfanta\Adapter\DoctrineORMAdapter;


/**
 * Post controller.
 *
 * @Route("/post")
 */
class PostController extends Controller
{
    /**
     * Lists all Post entities.
     *
     * @Route("/", name="post")
     * @Template()
     */
    public function indexAction()
    {
		$request = $this->getRequest();
		$page = $request->query->get('page');
		if( !$page ) $page = 1;
	
        $em = $this->getDoctrine()->getEntityManager();
        //$entities = $em->getRepository('ApplicationAnunciosBundle:Post')->findAll();

		$dql = "SELECT p FROM ApplicationAnunciosBundle:Post p WHERE p.type IS NOT NULL ORDER BY p.id DESC";
        $query = $em->createQuery($dql);
        $adapter = new DoctrineORMAdapter($query);

		$pagerfanta = new Pagerfanta($adapter);
		$pagerfanta->setMaxPerPage(10); // 10 by default
		$maxPerPage = $pagerfanta->getMaxPerPage();

		$pagerfanta->setCurrentPage($page); // 1 by default
		$entities = $pagerfanta->getCurrentPageResults();
		$routeGenerator = function($page) {
		    return '?page='.$page;
		};
		$view = new DefaultView();
		$html = $view->render($pagerfanta, $routeGenerator);
		



	 	$twig = $this->container->get('twig'); 
	    $twig->addExtension(new \Twig_Extensions_Extension_Text);

        return array('pager' => $html, 'entities' => $entities );
    }

    /**
     * Finds and displays a Post entity.
     *
     * @Route("/{id}/show", name="post_show")
     * @Template()
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Post entity.');
        }

        //$deleteForm = $this->createDeleteForm($id);

		$user = $em->getRepository('ApplicationUserBundle:User')->find($entity->getUserId());




			$session = $this->getRequest()->getSession();
			$contact = new \Application\UserBundle\Entity\Contact;
			$id = $session->get('id');
			if( $id ){
				$user_login = $em->getRepository('ApplicationUserBundle:User')->find($id);
				$contact->setName( $user_login->getName() );
				$contact->setEmail( $user_login->getEmail() );
			}
			$contact->setSubject( "RE: " . $entity->getTitle() );
			$contact_form = $this->createForm(new ContactType(), $contact);
			$contact_form_html = $contact_form->createView();



        return array(
            'entity'      => $entity,
            'user'      => $user,
			'contact_form' => $contact_form_html,
			);
    }

    /**
     * Displays a form to create a new Post entity.
     *
     * @Route("/new", name="post_new")
     * @Template()
     */
    public function newAction()
    {
	
		$session = $this->getRequest()->getSession();
		$session_id = $session->get('id');
		if( !$session_id ){
			return $this->redirect($this->generateUrl('user_welcome', array('back' => $_SERVER['REQUEST_URI'])));
		}
		
		//si no es post
		$request = $this->getRequest();
		
		if ($request->getMethod() != 'POST') {
        	$em = $this->getDoctrine()->getEntityManager();
			$user = $em->getRepository('ApplicationUserBundle:User')->find($session_id);
			$email = $user->getEmail();
		}
	
		$type = $request->query->get('type') ? 1 : 0;
		
	
        $entity = new Post();
        $entity->setType($type);
		$entity->setEmail( $email );
        $form   = $this->createForm(new PostType(), $entity);

        return array(
            'entity' => $entity,
            'form'   => $form->createView(),
			'type'   => $type
        );
    }

    /**
     * Creates a new Post entity.
     *
     * @Route("/create", name="post_create")
     * @Method("post")
     * @Template("ApplicationAnunciosBundle:Post:new.html.twig")
     */
    public function createAction()
    {
        $entity  = new Post();
        $request = $this->getRequest();
        $form    = $this->createForm(new PostType(), $entity);
        $form->bindRequest($request);

		// rellenar campos que faltan
		$session = $this->getRequest()->getSession();
		$user_id = $session->get('id');
		$entity->setUserId( $user_id );
		$entity->setDate( new \DateTime("now") );
		$entity->setFeatured( 0 );
		

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('post_show', array('id' => $entity->getId())));
            
        }

        return array(
            'entity' => $entity,
            'form'   => $form->createView()
        );
    }

    /**
     * Displays a form to edit an existing Post entity.
     *
     * @Route("/{id}/edit", name="post_edit")
     * @Template()
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Post entity.');
        }

		$session = $this->getRequest()->getSession();
		$user_id = $session->get('id');
		$admin = $session->get('admin');
		
		if( ( $entity->getUserId() == $user_id ) || $admin ){

	       $editForm = $this->createForm(new PostType(), $entity);
	        $deleteForm = $this->createDeleteForm($id);

	        return array(
	            'entity'      => $entity,
	            'edit_form'   => $editForm->createView(),
	            'delete_form' => $deleteForm->createView(),
	        );
	
		}else{
			$url = $this->generateUrl('post_show', array('id' => $entity->getId()));
			return $this->redirect($url);
		}
		
		
		
 
    }

    /**
     * Edits an existing Post entity.
     *
     * @Route("/{id}/update", name="post_update")
     * @Method("post")
     * @Template("ApplicationAnunciosBundle:Post:edit.html.twig")
     */
    public function updateAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Post entity.');
        }

		
		$session = $this->getRequest()->getSession();
		$user_id = $session->get('id');
		$admin = $session->get('admin');
		
		if( ( $entity->getUserId() == $user_id ) || $admin ){

	        $editForm   = $this->createForm(new PostType(), $entity);
	        $deleteForm = $this->createDeleteForm($id);

	        $request = $this->getRequest();

	        $editForm->bindRequest($request);

	        if ($editForm->isValid()) {
	            $em->persist($entity);
	            $em->flush();

	            return $this->redirect($this->generateUrl('post_show', array('id' => $id)));
	        }

	        return array(
	            'entity'      => $entity,
	            'edit_form'   => $editForm->createView(),
	            'delete_form' => $deleteForm->createView(),
	        );
	
		}else{
			$url = $this->generateUrl('post_show', array('id' => $entity->getId()));
			return $this->redirect($url);
		}
		
		
		
		

    }

    /**
     * Deletes a Post entity.
     *
     * @Route("/{id}/delete", name="post_delete")
     */
    public function deleteAction($id)
    {
		$em = $this->getDoctrine()->getEntityManager();
		$entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);
		if (!$entity) {
            throw $this->createNotFoundException('Unable to find Post entity.');
        }
		
		$session = $this->getRequest()->getSession();
		$user_id = $session->get('id');
		$admin = $session->get('admin');
		
		if( ( $entity->getUserId() == $user_id ) || $admin ){

            $em->remove($entity);
            $em->flush();

			$url = $this->generateUrl('post');
		}else{
			$url = $this->generateUrl('post_show', array('id' => $entity->getId()));
			
		}
		return $this->redirect($url);
    }

    /**
     * Search Post entities.
     *
     * @Route("/search", name="post_search")
     * @Template()
     */
    public function searchAction()
    {
		$request = $this->getRequest();
		$search = $request->query->get('q');
		$category_id = $request->query->get('c');
		$type = $request->query->get('t') ? 1 : 0;
		$location = $request->query->get('location');
		//$freelance = $request->query->get('freelance');
		
		$query = "SELECT p FROM ApplicationAnunciosBundle:Post p WHERE 1 = 1";
		
		if( $search ) $query .= " AND ( p.body LIKE '%".$search."%' OR p.title LIKE '%".$search."%' )";
		if( $category_id ) $query .= " AND p.category_id = " . $category_id;
		if( $location ) $query .= " AND p.location = '" . $location . "'";
		//if( $freelance ) $query .= " AND p.location IS NULL AND p.price IS NOT NULL";
		if( $type ) $query .= " AND p.type = " . $type;

		$query .= " ORDER BY p.id DESC";


		$entities = $this->get('doctrine')->getEntityManager()
		            ->createQuery($query)
		            ->getResult();
		
	 	$twig = $this->container->get('twig'); 
	    $twig->addExtension(new \Twig_Extensions_Extension_Text);
		
        return array('entities' => $entities, 'form_category' =>$category_id, 'form_type' => $type);
    }

    /**
     * Feed Post entities.
     *
     * @Route("/feed", name="post_feed", defaults={"_format"="xml"})
     * @Template()
     */
    public function feedAction()
    {

		$request = $this->getRequest();
		$category_id = $request->query->get('c');
		$type = $request->query->get('t') ? 1 : 0;
		
		$em = $this->getDoctrine()->getEntityManager();
		
		$query = "SELECT p FROM ApplicationAnunciosBundle:Post p WHERE 1 = 1";
		
		if( $category_id ) $query .= " AND p.category_id = " . $category_id;
		if( $type ) $query .= " AND p.type = " . $type;
		
		$query .= " ORDER BY p.id DESC";

		$entities = $this->get('doctrine')->getEntityManager()
		            ->createQuery($query)
		            ->setMaxResults(10)
		            ->getResult();
		
	 	$twig = $this->container->get('twig'); 
	    $twig->addExtension(new \Twig_Extensions_Extension_Text);
		
        return array('entities' => $entities, 'form_category' =>$category_id);
    }

    /**
     * Contact form
     *
     * @Route("/{id}/contact", name="post_contact")
     * @Template()
     */
    public function contactAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();
		$entity = $em->getRepository('ApplicationAnunciosBundle:Post')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }

		$form = $this->createForm(new ContactType());
		$result = 'no';
		
		$request = $this->getRequest();
		if ($request->getMethod() == 'POST') {
	        $form->bindRequest($request);
	
			
			

	        if ($form->isValid()) {

				
				$values = $form->getData();
				
				$toEmail = $entity->getEmail();// 'gafeman@gmail.com';
				
				extract( $values );

				$header = 'From: ' . $email . " \r\n";
				$header .= "X-Mailer: PHP/" . phpversion() . " \r\n";
				$header .= "Mime-Version: 1.0 \r\n";
				$header .= "Content-Type: text/plain";

				$mensaje = "Este mensaje fue enviado por " . $name . ". \r\n";
				$mensaje .= "Su e-mail es: " . $email . "\r\n";
				$mensaje .= "Mensaje: " . $body . " \r\n";
				$mensaje .= "Enviado el " . date('d/m/Y', time());




				$result = @mail($toEmail, $subject, utf8_decode($mensaje), $header);
				
				
				
				
				

	
	        }
	    }

        return array(
			'form' => $form->createView(),
            'entity'      => $entity,
			'result'      => $result,
			);


    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm()
        ;
    }

    /**
     * Admin Post entities.
     *
     * @Route("/admin", name="post_admin")
     * @Template()
     */
    public function adminAction()
    {
	
		$session = $this->getRequest()->getSession();
		if( !$session->get('admin') ){
			return $this->redirect('/');
		}
	
	
		$request = $this->getRequest();
		$page = $request->query->get('page');
		if( !$page ) $page = 1;
	
        $em = $this->getDoctrine()->getEntityManager();
        //$entities = $em->getRepository('ApplicationAnunciosBundle:Post')->findAll();

		$dql = "SELECT p FROM ApplicationAnunciosBundle:Post p ORDER BY p.id DESC";
        $query = $em->createQuery($dql);
        $adapter = new DoctrineORMAdapter($query);

		$pagerfanta = new Pagerfanta($adapter);
		$pagerfanta->setMaxPerPage(10); // 10 by default
		$maxPerPage = $pagerfanta->getMaxPerPage();

		$pagerfanta->setCurrentPage($page); // 1 by default
		$entities = $pagerfanta->getCurrentPageResults();
		$routeGenerator = function($page) {
		    return '?page='.$page;
		};
		$view = new DefaultView();
		$html = $view->render($pagerfanta, $routeGenerator);
		



	 	$twig = $this->container->get('twig'); 
	    $twig->addExtension(new \Twig_Extensions_Extension_Text);

        return array('pager' => $html, 'entities' => $entities );
    }

    /**
     * about page
     *
     * @Route("/about", name="post_about")
     * @Template()
     */
    public function aboutAction()
    {
        return array();
    }

    /**
     * how page
     *
     * @Route("/how", name="post_how")
     * @Template()
     */
    public function howAction()
    {
        return array();
    }
}
