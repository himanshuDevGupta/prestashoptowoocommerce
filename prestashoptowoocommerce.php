<?php
/**
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Prestashoptowoocommerce extends Module
{
    protected $config_form = false;
	private $html = '';
    private $postErrors = array();

    public function __construct()
    {
        $this->name = 'prestashoptowoocommerce';
		$this->prefix = Tools::strtoupper($this->name);
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'webgarh';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Prestashop to Woocommerce');
        $this->description = $this->l('Description goes here');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue($this->prefix.'_STATUS', false);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->callInstallTab() &&
			$this->registerHook('actionCartSave') &&
            $this->registerHook('actionAdminControllerSetMedia');
    }

    public function uninstall()
    {
        //Configuration::deleteByName($this->prefix.'_STATUS');
		//Configuration::deleteByName($this->prefix.'_API_KEY');
		//Configuration::deleteByName($this->prefix.'_API_SECRET');
		//Configuration::deleteByName($this->prefix.'_WEBSERVICE_KEY');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall() &&
		    $this->uninstallTab();
    }
	
	/**
     * Call Install Tabs
     */
    public function callInstallTab()
    {
        
        $this->installTab('AdminPrestshopWoocommerce', 'Prestashop to Woocommerce');
        $this->installTab('AdminPrestshopWoocommerceConfiguration', 'Configuration', 'AdminPrestshopWoocommerce');
        $this->installTab('AdminProductsWoocommerce', 'List Products', 'AdminPrestshopWoocommerce');

        return true;
    }
	
	/**
     * Install Tabs
     */
    public function installTab($className, $tabName, $tabParentName = false)
    {
        $tabParentId = 0; //Tab will display in Back-End
        if ($tabParentName) {
            $this->createPrestashopWoocommerceModuleTab($className, $tabName, $tabParentId, $tabParentName);
        } else {
            $this->createPrestashopWoocommerceModuleTab($className, $tabName, $tabParentId);
        }
    }
	
	/**
     * Create Tabs
     */
    public function createPrestashopWoocommerceModuleTab($className, $tabName, $tabParentId, $tabParentName = false)
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $className;
        $tab->name = array();

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $tabName;
        }

        if ($tabParentName) {
            $tab->id_parent = (int) Tab::getIdFromClassName($tabParentName);
        } else {
            $tab->id_parent = $tabParentId;
        }

        $tab->module = $this->name;

        return $tab->add();
    }
	
	/**
     * Uninstall Tabs
     */
    public function uninstallTab()
    {
        $moduleTabs = Tab::getCollectionFromModule($this->name);
        if (!empty($moduleTabs)) {
            foreach ($moduleTabs as $moduleTab) {
                $moduleTab->delete();
            }
        }
        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (Tools::isSubmit('submit'.$this->name)) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        $this->html .= $output.$this->renderForm();
        return $this->html;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit'.$this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Status'),
                        'name' => $this->prefix.'_STATUS',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module?'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'required' => true,
						'desc' => $this->l('Enter a valid Woocommerce Api Key'),
                        'name' => $this->prefix.'_API_KEY',
                        'label' => $this->l('Woocommerce Api Key'),
                    ),
					array(
                        'col' => 3,
                        'type' => 'text',
                        'required' => true,
						'desc' => $this->l('Enter a valid Woocommerce Api Secret'),
                        'name' => $this->prefix.'_API_SECRET',
                        'label' => $this->l('Woocommerce Api Secret'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
						'required' => true,
						'desc' => $this->l('Enter a valid Prestashop Webservice Key'),
                        'name' => $this->prefix.'_WEBSERVICE_KEY',
                        'label' => $this->l('Prestashop Webservice Key'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            $this->prefix.'_STATUS' => Configuration::get($this->prefix.'_STATUS'),
            $this->prefix.'_API_KEY' => Configuration::get($this->prefix.'_API_KEY'),
			$this->prefix.'_API_SECRET' => Configuration::get($this->prefix.'_API_SECRET'),
            $this->prefix.'_WEBSERVICE_KEY' => Configuration::get($this->prefix.'_WEBSERVICE_KEY'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/'.$this->name.'_front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/'.$this->name.'_front.css');
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookActionAdminControllerSetMedia()
    {
        $this->context->controller->addJS($this->_path.'views/js/'.$this->name.'_back.js');
        $this->context->controller->addCSS($this->_path.'views/css/'.$this->name.'_back.css');
    }
	
	/**
     * validate form data
     */
    protected function postValidation()
    {
        if (Tools::isSubmit('submit'.$this->name)) {
            if (!Tools::getValue($this->prefix.'_API_KEY')) {
                $this->postErrors[] = $this->l('Please Enter Api Key.');
            } if (!Tools::getValue($this->prefix.'_API_SECRET')) {
                $this->postErrors[] = $this->l('Please Enter Api Secret.');
            } if (!Tools::getValue($this->prefix.'_WEBSERVICE_KEY')) {
                $this->postErrors[] = $this->l('Please Enter Webservice Key.');
            }
        }
    }
	
	public function hookActionCartSave($params)
	{
		if (!empty(Tools::getValue('group_another'))) {
			$color = Tools::getValue('group_another')[1];
			$size = Tools::getValue('group_another')[2];
			$color_comb = Db::getInstance()->executeS('SELECT `id_product_attribute` FROM '._DB_PREFIX_.'product_attribute_combination WHERE id_attribute="'.$color.'"');
			
			$color_product_attr = array();
			foreach ($color_comb as $single_color_comb) {
				$color_product_attr[] = $single_color_comb['id_product_attribute'];
			}
			
			$size_comb = Db::getInstance()->executeS('SELECT `id_product_attribute` FROM '._DB_PREFIX_.'product_attribute_combination WHERE id_attribute="'.$size.'"');
			
			$size_product_attr = array();
			foreach ($size_comb as $single_size_comb) {
				$size_product_attr[] = $single_size_comb['id_product_attribute'];
			}
			$result=array_intersect($color_product_attr,$size_product_attr);
			
			// Check already added to cart or not
			$get_product = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'cart_product WHERE id_cart="'.$params['cart']->id.'" AND id_product="'.Tools::getValue('id_product').'" AND id_product_attribute="'.reset($result).'"');
			
			if (!empty($get_product)) {
				$qty = $get_product['quantity']+Tools::getValue('qty');
				$update='UPDATE '._DB_PREFIX_.'cart_product SET quantity="'.$qty.'" WHERE id_cart="'.$params['cart']->id.'" AND id_product="'.Tools::getValue('id_product').'" AND id_product_attribute="'.reset($result).'"';
				Db::getInstance()->execute($update);
			} else {
				// Insert into cart
				Db::getInstance()->insert('cart_product', array(
					'id_product' => (int)Tools::getValue('id_product'),
					'id_product_attribute' => reset($result),
					'id_cart' => $params['cart']->id,
					'id_address_delivery' => $params['cart']->id_address_delivery,
					'id_shop' => $params['cart']->id_shop,
					'quantity' => Tools::getValue('qty')
				));
			}
		}
		
		
	}
}
