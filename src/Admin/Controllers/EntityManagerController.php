<?php
namespace Mouf\Doctrine\ORM\Admin\Controllers;

use Mouf\MoufUtils;

use Mouf\InstanceProxy;

use Mouf\Validator\InstancesClassValidator;

use Doctrine\ORM\Tools\SchemaTool;

use Mouf\Actions\InstallUtils;
use Mouf\MoufManager;
use Mouf\Mvc\Splash\Controllers\Controller;

/**
 * The controller managing the install process.
 * It will query the database details.
 *
 * @Component
 */
class EntityManagerController extends Controller  {
	
	public $selfedit;
	
	/**
	 * The active MoufManager to be edited/viewed
	 *
	 * @var MoufManager
	 */
	public $moufManager;
	
	/**
	 * The template used by the main page for mouf.
	 *
	 * @Property
	 * @Compulsory
	 * @var TemplateInterface
	 */
	public $template;
	
	/**
	 * The content block the template will be writting into.
	 *
	 * @Property
	 * @Compulsory
	 * @var HtmlBlock
	 */
	public $contentBlock;
	
	protected $sourceDirectory;
	protected $entitiesNamespace;
	protected $proxyNamespace;
	protected $daoNamespace;
	protected $psrMode;
	protected $instanceName;
	
	/**
	 * Displays the first install screen.
	 * 
	 * @Action
	 * @Logged
	 * @param string $selfedit If true, the name of the component must be a component from the Mouf framework itself (internal use only) 
	 */
	public function defaultAction($name = null, $selfedit = "false") {
		$this->selfedit = $selfedit;
		$name = $name ? $name : "entityManager";
		
		$this->instanceName = $name;
		
		if ($selfedit == "true") {
			$this->moufManager = MoufManager::getMoufManager();
		} else {
			$this->moufManager = MoufManager::getMoufManagerHiddenInstance();
		}
		
		$autoloadNamespaces = MoufUtils::getAutoloadNamespaces2();
		$this->psrMode = $autoloadNamespaces['psr'];
		
		$this->autoloadDetected = true;
		if ($this->moufManager->instanceExists($name)){
			$instance = $this->moufManager->getInstanceDescriptor($name);
			$this->sourceDirectory = $instance->getProperty("sourceDirectory")->getValue();
			$this->entitiesNamespace = $instance->getProperty("entitiesNamespace")->getValue();
			$this->proxyNamespace = $instance->getProperty("proxyNamespace")->getValue();
			$this->daoNamespace = $instance->getProperty("daoNamespace")->getValue();
		}else{
			if ($autoloadNamespaces) {
				$rootNamespace = $autoloadNamespaces[0]['namespace'].'\\';
				$this->sourceDirectory = $autoloadNamespaces[0]['directory'];
				$this->entitiesNamespace = $rootNamespace."Model\\Entities";
				$this->proxyNamespace = $rootNamespace."Model\\Proxies";
				$this->daoNamespace = $rootNamespace."Model\\DAOs";
			} else {
				$this->autoloadDetected = false;
				$this->entitiesPath = "src/path_to_entities";
				$this->proxyDir = "src/path_to_proxies";
				$this->proxyNamespace = "YOUR_APP_NAMESPACE\\PATH\\TO\\PROXIES";
			}
		}
		
		$this->contentBlock->addFile(__DIR__."/../views/install.php", $this);
		$this->template->toHtml();
	}
	
	/**
	 * Displays the "schema generation screen"
	 *
	 * @Action
	 * @Logged
	 * @param string $selfedit If true, the name of the component must be a component from the Mouf framework itself (internal use only)
	 */
	public function install($sourceDirectory, $entitiesNamespace, $proxyNamespace, $daoNamespace, $instanceName, $selfedit) {
		if ($selfedit == "true") {
			$this->moufManager = MoufManager::getMoufManager();
		} else {
			$this->moufManager = MoufManager::getMoufManagerHiddenInstance();
		}
		
		$dbalConnection = $this->moufManager->getInstanceDescriptor("dbalConnection");
		$eventManager = $dbalConnection->getProperty("eventManager")->getValue();
		
		if (!$this->moufManager->instanceExists($instanceName)){
			$em = $this->moufManager->createInstance("Mouf\\Doctrine\\ORM\\EntityManager");
			$em->setName($instanceName);
			$config = $this->moufManager->createInstance("Doctrine\\ORM\\Configuration");
			$config->setName("doctrineConfiguration");
		}else{
			$em = $this->moufManager->getInstanceDescriptor($instanceName);
			$config = $em->getProperty("config")->getValue();
		}

		$entitiesPath = $sourceDirectory . str_replace("\\", "/", $entitiesNamespace);
		$proxyPath = $sourceDirectory . str_replace("\\", "/", $proxyNamespace);
		$daoPath = $sourceDirectory . str_replace("\\", "/", $daoNamespace);
		
		$config->getProperty("metadataDriverImpl")->setOrigin("php")->setValue('return $this->newDefaultAnnotationDriver(ROOT_PATH . "'. $entitiesPath.'");');
		$config->getProperty("proxyDir")->setValue($proxyPath);
		$config->getProperty("proxyNamespace")->setValue($proxyNamespace);
		
		$em->getProperty("conn")->setValue($dbalConnection);
		$em->getProperty("config")->setValue($config);
		$em->getProperty("eventManager")->setValue($eventManager);

		$em->getProperty("sourceDirectory")->setValue($sourceDirectory);
		$em->getProperty("entitiesNamespace")->setValue($entitiesNamespace);
		$em->getProperty("proxyNamespace")->setValue($proxyNamespace);
		$em->getProperty("daoNamespace")->setValue($daoNamespace);
	
		
		$proxy = new InstanceProxy($instanceName);
		try{
			$proxy->generateSchemaFromEntities();
			$daoData = $proxy->generateDAOs();
			
			foreach ($daoData as $fullClassName => $className) {
				if (!$this->moufManager->instanceExists(lcfirst($className))){
					$daoInstance = $this->moufManager->createInstance($fullClassName);
				}else{
					$daoInstance = $this->moufManager->getInstanceDescriptor(lcfirst($daoClass));
				}
				$daoInstance->getProperty("entityManager")->setValue($em);
			}
			
		}catch (\Exception $e){
			$this->errors[] = $e;
		}
		
		$this->moufManager->rewriteMouf();
		//redirect User to right view
		var_dump($this->errors);
	}
	
}