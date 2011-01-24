<?php
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2010, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2010, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       cake
 * @subpackage    cake.app
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package       cake
 * @subpackage    cake.app
 */
class AppController extends Controller {
	public $components = array(
		'Auth',
		'Session',
		'DebugKit.Toolbar'
	);

/**
 * A list of the resources that have been included in access control.
 * This is used by the Nssrbac plugin
 *
 * @var array
 * @access public
 */
	public $protectedResources = array();

/**
 * permissionsMap
 *
 * @var array
 * @access public
 */
	public $permissionsMap = array(
		'create' => array('add'),
		'read' => array('index', 'view'),
		'update' => array('edit'),
		'delete' => array('delete'),
	);

/**
 * beforeFilter Callback
 *
 * @return void
 * @access public
 */
	public function beforeFilter() {
		$admin = Configure::read('Routing.admin');

		// added for the Nssrbac plugin
		$authId = $this->Auth->user('id');
		if ($authId && !is_array($this->Session->read('Auth.Role'))) {
			$this->setRoles();
		}

		// required for the Nssrbac plugin
		$this->Auth->authorize = 'controller';

		// required to populate $this->protectedResources array
		$this->setResources();

		$this->Auth->loginAction = array('controller' => 'users', 'action' => 'login', 'prefix' => $admin, $admin => false, 'plugin' => null);
		$this->Auth->logoutRedirect = array('controller' => 'users', 'action' => 'login', 'prefix' => $admin, $admin => false, 'plugin' => null);
		$this->Auth->loginError = __('Invalid username / password combination.  Please try again', true);
		$this->Auth->loginRedirect = array('controller' => 'articles', 'action' => 'index', 'prefix' => $admin, $admin => false, 'plugin' => null);
	}

/**
 * isAuthorized callback; does the majority of the heavy lifting for the Nssrbac plugin
 * Required for Auth->authorize('controller')
 *
 * @return boolean
 * @access public
 */
	public function isAuthorized() {
		if (in_array($this->name, $this->protectedResources)) {
			$permissions = $this->getPermissions($this->name);
			if (in_array($this->action, $permissions)) {
				return true;
			} else {
				return false;
			}
		}
		return true;
	}

/**
 * Get a list of all the resources that are access controlled and populate $this->protectedResources
 *
 * @return boolean
 * @access public
 */
	public function setResources() {
		$Resource = ClassRegistry::init('Nssrbac.Resource');
		$resources = $Resource->find('all');
		foreach($resources as $resource) {
			$this->protectedResources[] = Inflector::camelize(Inflector::pluralize($resource['Resource']['name']));
		}
		return true;
	}

/**
 * Combines the user roles into the session variable
 *
 * @return boolean
 * @access public
 */
	public function setRoles() {
		$this->User = ClassRegistry::init('User');
		$roles = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $this->Auth->user('id'),
			),
		));
		$auth = $this->Session->read('Auth');
		$roleAuth = am($roles, $auth);
		$this->Session->write('Auth', $roleAuth);
		return true;
	}

/**
 * Get a list of authorized methods for a particular user/role to a resource
 *
 * @param string $resourceName
 * @return array $permittedMethods
 * @access public
 */
	public function getPermissions($resourceName = null) {
		$permissionNames = array();
		$permittedMethods = array();
		$Resource = ClassRegistry::init('Nssrbac.Resource');
		$Permission = ClassRegistry::init('Nssrbac.Permission');
		$roles = $this->Session->read('Auth');
		$myRoleIds = Set::Extract('/Role/id', $roles);
		$permissions = $Permission->find('all', array(
			'conditions' => array(
				'Resource.name' => Inflector::singularize($resourceName),
			),
			'contain' => array(
				'Role' => array(
					'conditions' => array('Role.id' => $myRoleIds)
				),
				'Resource'
			),
			'fields' => array('Permission.name'),
		));

		foreach ($permissions as $p => $permission) {
			if (!empty($permission['Role'])) {
				$permissionNames[] = $permission['Permission']['name'];
			}
		}
		if (is_array($permissionNames)) {
			foreach ($permissionNames as $name) {
				$methods = $this->permissionsMap[$name];
				foreach ($methods as $method) {
					$permittedMethods[] = $method;
				}
			}
			return $permittedMethods;
		}
	}
}
