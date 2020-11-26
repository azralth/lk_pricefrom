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

class Lk_Pricefrom extends Module
{
    private $templateFile;

    public function __construct()
    {
        $this->name = 'lk_pricefrom';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Lk Interactive';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Lk Interactive - Price From', array(), 'Modules.Lkpricefrom.Admin');
        $this->description = $this->trans('Add a price from label in product page and Category page', array(), 'Modules.Lkpricefrom.Admin');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('displayProductPriceBlock') &&
            $this->installFixtures() &&
            $this->disableDevice(Context::DEVICE_MOBILE);
    }

    public function uninstall()
    {
        Configuration::deleteByName('LK_PRICE_FROM_ON_LISTING_PAGE');
        Configuration::deleteByName('LK_PRICE_FROM_LABEL');
        return parent::uninstall();
    }

    protected function installFixtures()
    {
        Configuration::updateValue('LK_PRICE_FROM_ON_LISTING_PAGE', false);
        $languages = Language::getLanguages(false);

        foreach ($languages as $lang) {
            $this->installFixture((int)$lang['id_lang']);
        }

        return true;
    }

    protected function installFixture($id_lang)
    {
        $values = [];
        $values['LK_PRICE_FROM_LABEL'][(int)$id_lang] = '';
        Configuration::updateValue('LK_PRICE_FROM_LABEL', $values['LK_PRICE_FROM_LABEL']);
    }

    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitLkFromPriceConf')) == true) {
            $this->postProcess();
        }

        return $this->renderForm();
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
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitLkFromPriceConf';
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
                        'label' => $this->trans('Show on listing product', array(), 'Modules.Lkpricefrom.Admin'),
                        'name' => 'LK_PRICE_FROM_ON_LISTING_PAGE',
                        'is_bool' => true,
                        'desc' => $this->trans('Say true if you want to show label From price in listing category page',array(), 'Modules.Lkpricefrom.Admin'),
                        'values' => array(
                            array(
                                'id' => 'show_on_listing_page',
                                'value' => true,
                                'label' => $this->trans('Show',array(),'Modules.Lkpricefrom.Admin')
                            ),
                            array(
                                'id' => 'show_off_listing_page',
                                'value' => false,
                                'label' => $this->trans('Hide',array(),'Modules.Lkpricefrom.Admin')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Price from label', array(),'Modules.Lkpricefrom.Admin'),
                        'name' => 'LK_PRICE_FROM_LABEL',
                        'desc' => $this->trans('Use this field to custom Price from label.', array(),'Modules.Lkpricefrom.Admin'),
                        'lang' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        $languages = Language::getLanguages(false);
        $fields = array();

        $fields['LK_PRICE_FROM_ON_LISTING_PAGE'] = Configuration::get('LK_PRICE_FROM_ON_LISTING_PAGE');
        foreach ($languages as $lang) {
            $fields['LK_PRICE_FROM_LABEL'][$lang['id_lang']] = Tools::getValue('LK_PRICE_FROM_LABEL_'.$lang['id_lang'], Configuration::get('LK_PRICE_FROM_LABEL', $lang['id_lang']));
        }

        return $fields;
    }

    protected function postProcess()
    {
        if (Tools::isSubmit('submitLkFromPriceConf')) {
            $languages = Language::getLanguages(false);
            $values = array();

            foreach ($languages as $lang) {
                $values['LK_PRICE_FROM_LABEL'][$lang['id_lang']] = Tools::getValue('LK_PRICE_FROM_LABEL_'.$lang['id_lang']);
            }

            Configuration::updateValue('LK_PRICE_FROM_LABEL', $values['LK_PRICE_FROM_LABEL']);
            Configuration::updateValue('LK_PRICE_FROM_ON_LISTING_PAGE', Tools::getValue('LK_PRICE_FROM_ON_LISTING_PAGE'));

            $this->_clearCache($this->templateFile);

            return $this->displayConfirmation($this->trans('The settings have been updated.', array(), 'Admin.Notifications.Success'));
        }

        return '';
    }

    public function hookDisplayProductPriceBlock($params)
    {
        $show_in_category = Configuration::get('LK_PRICE_FROM_ON_LISTING_PAGE');
        $type = $params['type'];
        if ($type == 'after_price' || ($type == 'before_price' && $show_in_category)) {
            $product_id = (int)Tools::getValue('id_product');
            $lang = $this->context->language->id;
            $label = Configuration::get('LK_PRICE_FROM_LABEL', $lang);
            $product = new Product($product_id);
            $attributes = $product->getAttributesResume($lang);
            $lowestprice = (int)99999999999;
            if (!empty($attributes)) {
                foreach ($attributes as $attribute) {
                    $stock = StockAvailable::getQuantityAvailableByProduct($product_id, $attribute['id_product_attribute'], 1);
                    if ($stock > 0) {
                        $oldprice = isset($attributePrice) ? $attributePrice : null;
                        $attributePrice = Product::getPriceStatic($product_id, true, $attribute['id_product_attribute']);
                        $oldprice = !isset($lowestprice) ? $oldprice : $lowestprice;
                        if($attributePrice < $lowestprice) {
                            $lowestprice = $attributePrice;
                        }
                    }
                }
                if (isset($lowestprice)) {
                    $this->context->smarty->assign(array(
                        'label_from_price' => $label,
                        'lowest_price' => Tools::displayPrice($lowestprice)
                    ));
                }
                return $this->context->smarty->fetch(_PS_MODULE_DIR_.$this->name.'/views/templates/front/hook/display-price.tpl');
            }
        }
    }
}
