<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController{

    public function indexAction(){

    	$request = $this->getRequest();
    	$result = array();

    	if($request->isPost()){

    		try{

	    		$nome = $request->getPost("nome");
	    		$cpf = $request->getPost("cpf");
	    		$salario = $request->getPost("salario");

	    		$funcionario = new \Application\Model\Funcionario();
	    		$funcionario->setNome($nome);
	    		$funcionario->setCpf($cpf);
	    		$funcionario->setSalario($salario);

	    		$em = $this->getServiceLocator()->get("Doctrine\ORM\EntityManager");
	    		$em->persist($funcionario);
	    		$em->flush();

	    		//$result["resp"] = var_dump($funcionario);
	    		$result["resp"] = "funcionário adicionado!";

	    	}catch(Exception $e){

	    	}

    	}

        return new ViewModel($result);

    }

    public function listarAction(){

    	$em = $this->getServiceLocator()->get("Doctrine\ORM\EntityManager");
    	$lista = $em->getRepository("Application\Model\Funcionario")->findAll();
    	return new ViewModel(array('lista' => $lista));

    }

    public function excluirAction(){

    	$id = $this->params()->fromRoute("id", 0);
    	$em = $this->getServiceLocator()->get("Doctrine\ORM\EntityManager");
    	$funcionario = $em->find("Application\Model\Funcionario", $id);
    	$em->remove($funcionario);
    	$em->flush();

    	return $this->redirect()->toRoute('application/default', 
    			array('controller' => 'index', 'action' => 'listar'));

    }

    public function editarAction(){

    	$id = $this->params()->fromRoute("id", 0);
    	$em = $this->getServiceLocator()->get("Doctrine\ORM\EntityManager");

    	$funcionario = $em->find("Application\Model\Funcionario", $id);
    	$request = $this->getRequest();
    	if($request->isPost()){

    		try {

    			$nome = $request->getPost("nome");
    			$cpf = $request->getPost("cpf");
    			$salario = $request->getPost("salario");

    			$funcionario->setNome($nome);
    			$funcionario->setCpf($cpf);
    			$funcionario->setSalario($salario);

    			$em = $this->getServiceLocator()->get("Doctrine\ORM\EntityManager");
    			$em->merge($funcionario);
    			$em->flush();
    			
    		} catch (Exception $e) {
    			
    		}

    		return $this->redirect()->toRoute('application/default', 
    				array('controller' => 'index', 'action' => 'listar'));

    	}

    	return new ViewModel(array('f' => $funcionario));

    }

}
