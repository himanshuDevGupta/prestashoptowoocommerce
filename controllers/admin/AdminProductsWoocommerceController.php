<?php
/**
* 2007-2019 PrestaShop
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
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once _PS_MODULE_DIR_.'prestashoptowoocommerce/classes/PSWebServiceLibrary.php';
$autoloader = 'vendor/autoload.php';
require_once $autoloader;
use Automattic\WooCommerce\Client;

class AdminProductsWoocommerceController extends ModuleAdminController
{
    public $content = '';
    private $postErrors = array();
    public function __construct()
    {
        $this->context = Context::getContext();
        $this->bootstrap = true;

        parent::__construct();
        $this->toolbar_title = $this->l('List Products');
    }

    /**
     * Initialize Content
     */
    public function initContent()
    {
        $shop_path = Tools::getHttpHost(true).__PS_BASE_URI__;
        $webservice_key = Configuration::get('PRESTASHOPTOWOOCOMMERCE_WEBSERVICE_KEY');

        try {
            $db_products = $this->getAllDbProducts();
			
			$products = array();
			foreach ($db_products as $p) {
				if (!in_array($p['id_product'], $products)) {
					$products[] = $p['id_product'];
				}
			}

            $webService = new PrestaShopWebservice($shop_path, $webservice_key, false);
            // Here we set the option array for the Webservice : we want products resources
            $opt['resource'] = 'products';
            $opt['display'] = 'full';
            // Call
            $xml = $webService->get($opt);
            $resources = $xml->products->product;
            $all_data = array();
            foreach ($resources as $resource) {
                $product_data = (array)$resource;
                $name_data = (array) $product_data['name'];
                if (!in_array($product_data['id'], $products)) {
                    $data[$product_data['id']] = array('reference' => (!empty($product_data['reference'])) ? $product_data['reference'] : '',
                        'id' => $product_data['id'],
                        'price' => $product_data['price'],
                        'name' => $name_data['language'][$this->context->language->id]
                    );
                    $all_data = $data;
                }
            }
            krsort($all_data);
            $this->context->smarty->assign('data', $all_data);
        } catch (PrestaShopWebserviceException $e) {
            // Here we are dealing with errors
            $trace = $e->getTrace();
            if ($trace[0]['args'][0] == 404) $api_error = 'Bad ID';
            else if ($trace[0]['args'][0] == 401) $api_error = 'Bad auth key';
            else $api_error = 'Other error';
            $this->context->smarty->assign('error', $api_error);
        }

        if (Tools::getIsset('submit') && Tools::getValue('submit') == 'submit') {
            if (!empty($_POST['product_id'])) {
                $webService = new PrestaShopWebservice($shop_path, $webservice_key, false);
                // Get Selected Products
                $opt = array (
                    'resource' => 'products',
                    'display' => 'full',
                    'filter[id]' => '['.implode('|', $_POST['product_id']).']'
                );
                // Call
                $xml = $webService->get($opt);
                $resources = $xml->products->product;

                $all_products = array();
                foreach ($resources as $resource) {
                    $product_data = (array)$resource;
                    $categories = array();
                    foreach ($product_data['associations']->categories->category as $single_category) {
                        $cat = (array) $single_category;
                        $categories[] = $cat['id'];
                    }

                    // Get Categories
                    $opt_cats = array (
                        'resource' => 'categories',
                        'display' => 'full',
                        'filter[id]' => '['.implode('|', $categories).']'
                    );
                    $xml_cats = $webService->get($opt_cats);
                    $resources_cat = $xml_cats->categories->category;

                    $cat_names = array();
                    foreach ($resources_cat as $single_cat) {
                        $cat_data = (array) $single_cat;
                        $name_data = (array) $cat_data['name'];
                        $name = $name_data['language'][$this->context->language->id];
                        $cat_names[] = $name;
                    }

                    $name_data = (array) $product_data['name'];
                    $image_type = 'home_default';
                    $link = new Link();
                    $product = new Product((int)$product_data['id'], false, $this->context->language->id);

                    $url = $link->getProductLink($product);
                    $img = $product->getCover($product->id);
                    $img_url = $link->getImageLink(isset($product->link_rewrite) ? $product->link_rewrite : $product->name, (int)$img['id_image'], $image_type);
                    $data = array('reference' => (!empty($product_data['reference'])) ? $product_data['reference'] : '',
                            'id' => $product_data['id'],
                            'price' => $product_data['price'],
                            'short_desc' => $product->description_short,
                            'long_desc' => $product->description,
                            'name' => $name_data['language'][$this->context->language->id],
                            'img' => $img_url,
                            'categories' => $cat_names,
                            'url' => $url
                        );
                    $all_products[] = $data;
                }

                $woocommerce = new Client(
                    'http://159.65.150.55/wooapi',
                    Configuration::get('PRESTASHOPTOWOOCOMMERCE_API_KEY'),
                    Configuration::get('PRESTASHOPTOWOOCOMMERCE_API_SECRET'),
                    array(
                        'wp_api'  => true,
                        'version' => 'wc/v3',
                    )
                );

                // Get Product Categoires
                $all_categories = $woocommerce->get('products/categories');
                $wp_categories = array();
                foreach ($all_categories as $single_category) {
                    if (!array_key_exists($single_category->id, $wp_categories)) {
                        $wp_categories[$single_category->id] = $single_category->name;
                    }
                }

                // Insert into
			    $counter = 0;
                foreach ($all_products as $single_product) {
                    // Insert Product
                    $this->insertProduct($woocommerce, $single_product, $wp_categories);
                    // Save Product Inserted to Woocommerce
                    $this->insertProductToDb($single_product['id'], $single_product['name'], $single_product['reference']);
                    $counter++;
                }
				Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminProductsWoocommerce'));
            } else {
				$this->context->cookie->__set('error', 'Please choose atleast one product.');
				Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminProductsWoocommerce'));
            }
        }
		$this->context->smarty->assign('token', Tools::getAdminTokenLite('AdminProductsWoocommerce'));
		$this->content .= $this->context->smarty->fetch(_PS_MODULE_DIR_.'prestashoptowoocommerce/views/templates/admin/products.tpl');
        parent::initContent();
    }

    public function insertCategory($woocommerce, $cat_name)
    {
        $cat_data = array(
            'name' => $cat_name,
            'image' => array(
                'src' => ''
            )
        );
        $res = $woocommerce->post( 'products/categories', $cat_data );
        return $res->id;
    }

    public function insertProduct($woocommerce, $pdata, $wp_categories)
    {
        $p_categories = array();
        foreach ($pdata['categories'] as $single_cat) {
            if (!in_array($single_cat, $wp_categories)) {
                //Insert Category
                $cat_id = $this->insertCategory($woocommerce, $single_cat);
            } else {
                $cat_id = array_search($single_cat, $wp_categories);
            }
            $p_categories[] = array('id' => $cat_id);
        }

        // Get Product Attributes
        $wp_all_attributes = $this->getAttributes($woocommerce);
        $check_attr = array('12 months','24 months','36 months');
        $attr_id = 0;
        $attr_name = '';
        foreach ($check_attr as $main_attr) {
            if (!empty($wp_all_attributes)) {
                if (!in_array($main_attr, $wp_all_attributes)) {
                    $attribute = $this->insertAttribute($woocommerce, $main_attr);
                    $attr_id = $attribute->id;
                    $attr_name = $attribute->name;
                } else {
                    $attr_id = array_search($main_attr, $wp_all_attributes);
                    $attr_name = $main_attr;
                }
            }else {
                $attribute = $this->insertAttribute($woocommerce, $main_attr);
                $attr_id = $attribute->id;
                $attr_name = $attribute->name;
            }
        }

        $prod_data = array(
            'name'          => $pdata['name'],
            'type'          => 'variable-subscription',
            'regular_price' => $pdata['price'],
            'description'   => $pdata['long_desc'],
            'short_description' => $pdata['short_desc'],
            'images' => array(
                array(
                    'src'      => $pdata['img'],
                    'position' => 0,
                ),
            ),
            'categories'    => $p_categories,
        );

        $p = $woocommerce->post( 'products', $prod_data );
    }

    public function getAttributes($woocommerce)
    {
        $attributes = $woocommerce->get('products/attributes');
        $wp_all_attributes = array();
        if (!empty($attributes)) {
            foreach ($attributes as $attr) {
                if (!in_array($attr->name, $wp_all_attributes)) {
                    $wp_all_attributes[$attr->id] = $attr->name;
                }
            }
        }
        return $wp_all_attributes;
    }

    public function insertAttribute($woocommerce, $name)
    {
        $data = array(
            'name' => $name,
            'slug' => str_replace(' ', '-', $name),
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => true
        );
        $attribute_data = $woocommerce->post('products/attributes', $data);
        return $attribute_data;
    }

    public function insertProductVariation($woocommerce, $variation_data, $pid)
    {
        return $woocommerce->post('products/'.$pid.'/variations', $variation_data);
    }
	
    public function insertProductToDb($pid, $p_name, $p_reference)
    {
        Db::getInstance()->insert('prestashoptowoocommerce', array(
            'id_product' => (int)$pid,
            'product_name' => pSQL($p_name),
            'reference' => pSQL($p_reference)
        ));
    }

    public function getAllDbProducts()
    {
        $results = Db::getInstance()->executeS('SELECT `id_product` FROM '._DB_PREFIX_.'prestashoptowoocommerce');
		return $results;
    }

    /**
     * Initialize Header Toolbar
     */
    public function initToolbar()
    {
        parent::initToolbar();
    }
}
