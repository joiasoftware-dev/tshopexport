<?php

/**
 * copyright Joia Software Solutions [https://www.joiasoftware.it]
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to Commercial Licence Copyright
 * You can modifify this and use only on the site declared when you bought it.
 *
 *    @author    Joia Software Solutions <ticket@joiasoftware.it>
 *    @copyright Joia Software Solutions - Italy
 *    @license   Commercial Licence
 */

if (!defined('_PS_VERSION_'))
    exit();

class TshopExport extends Module
{

    public $lang_default;
    public $languages;
    //IMPORTANT define ID_ATTRIBUTE for COLORI
    public $colori = array(2,5);

    public function __construct()
    {
        $this->name = 'tshopexport';
        $this->tab = 'quick_bulk_update';
        $this->version = '1.1.1';
        $this->author = 'Joia Software Solutions';
        $this->need_instance = 0;
        //$this->module_key = '3470670a179c6b58d5db00cc36463d8c';
        $this->bootstrap = true;
        parent::__construct();
        
        $this->displayName = $this->l('T-SHOP Export');
        $this->description = $this->l('Export Prestashop data into T-SHOP.');
        $this->languages = Language::getLanguages(false, false);
        $this->lang_default = Configuration::get("PS_LANG_DEFAULT");
    }

    public function install()
    {
        return parent::install() && $this->registerHook('backOfficeHeader');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function hookBackOfficeHeader()
    {
        $this->context->controller->addJquery();
        $this->context->controller->addJS($this->_path . 'views/js/back.js');
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');
    }

    public function getContent()
    {
        $this->smarty->assign(array(
            'export_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
            'export_message' => 'Export Completed'
        ));
        
        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true)
            return $this->display(__FILE__, 'views/templates/admin/joia.tpl');
    }

    private function exportManufacturer()
    {
        $brand = Manufacturer::getManufacturers(false, $this->lang_default, false);
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('manufactures');
        foreach ($brand as $i => $obj) {
            $data_upd = DateTime::createFromFormat('Y-m-d H:i:s', pSQL($obj['date_upd']))->format("d-m-Y H:i:s");
            $xml->startElement('manufacturer');
            $xml->writeAttribute('id_prestashop', $obj['id_manufacturer']);
            $xml->writeAttribute('active', $obj['active']);
            $xml->writeAttribute('date_upd', $data_upd);
            $xml->startElement('name');
            $xml->writeCdata($obj['name']);
            $xml->endElement();
            $xml->startElement("langs");
            foreach ($this->languages as $lang) {
                $manufacturer = new Manufacturer((int) $obj['id_manufacturer'], $lang['id_lang']);
                $xml->startElement("lang");
                $xml->writeAttribute('id', $lang['id_lang']);
                // Short
                $xml->startElement('short');
                $xml->writeCdata($manufacturer->short_description);
                $xml->endElement();
                // Long
                $xml->startElement('long');
                $xml->writeCdata($manufacturer->description);
                $xml->endElement();
                // Meta
                $xml->startElement('metatitle');
                $xml->writeCdata($manufacturer->meta_title);
                $xml->endElement();
                $xml->startElement('metakey');
                $xml->writeCdata($manufacturer->meta_keywords);
                $xml->endElement();
                $xml->startElement('metadescription');
                $xml->writeCdata($manufacturer->meta_description);
                $xml->endElement();
                $xml->endElement();
            }
            $xml->endElement();
            $xml->endElement();
            if (0 == $i % 1000) {
                file_put_contents(dirname(__FILE__) . '/export/manufacturer.xml', $xml->flush(true), FILE_APPEND);
            }
        }
        
        $xml->endElement();
        file_put_contents(dirname(__FILE__) . '/export/manufacturer.xml', $xml->flush(true), FILE_APPEND);
        return 'manufacturer.xml';
    }

    private function exportAttribute()
    {
        $element = AttributeGroup::getAttributesGroups($this->lang_default);
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('attributes');
        foreach ($element as $i => $obj) {
            if (isset($obj['date_upd']))
                $data_upd = DateTime::createFromFormat('Y-m-d H:i:s', pSQL($obj['date_upd']))->format("d-m-Y H:i:s");
            else
                $data_upd = date("d-m-y H:i:s");
                
            $sql_is_used = "SELECT if(b.id_attribute is null,0,COUNT(*)) as conta from `"._DB_PREFIX_
            ."attribute` a JOIN `"._DB_PREFIX_."attribute_group_lang` g on a.id_attribute_group=g.id_attribute_group "
            ." LEFT OUTER JOIN `"._DB_PREFIX_."product_attribute_combination` b on a.id_attribute =b.id_attribute where g.id_attribute_group = ".(int)$obj['id_attribute_group']
            ." GROUP by g.id_attribute_group ";
            if(!Db::getInstance()->getValue($sql_is_used)){
                continue;
            }
            $xml->startElement('attribute');
            $xml->writeAttribute('id_prestashop', $obj['id_attribute_group']);
            if ($obj['is_color_group'] == 1 or in_array((int)$obj['id_attribute_group'],$this->colori))
                $xml->writeAttribute('id_color', '1');
            else
                $xml->writeAttribute('id_size', '1');
            $xml->writeAttribute('active', '1');
            $xml->writeAttribute('date_upd', $data_upd);
            $xml->startElement('name');
            $xml->writeCdata($obj['name']);
            $xml->endElement();
            $xml->startElement("langs");
            foreach ($this->languages as $lang) {
                $xml->startElement("lang");
                $xml->writeAttribute('id', $lang['id_lang']);
                $list = AttributeGroup::getAttributes($lang['id_lang'], $obj['id_attribute_group']);
                foreach ($list as $value) {
                    $xml->startElement("name");
                    $xml->writeAttribute('id_prestashop', $value['id_attribute']);
                    $xml->writeCdata($value['name']);
                    $xml->endElement();
                }
                $xml->endElement();
            }
            $xml->endElement();
            $xml->endElement();
            if (0 == $i % 1000) {
                file_put_contents(dirname(__FILE__) . '/export/attribute.xml', $xml->flush(true), FILE_APPEND);
            }
        }
        
        $xml->endElement();
        file_put_contents(dirname(__FILE__) . '/export/attribute.xml', $xml->flush(true), FILE_APPEND);
        return 'attribute.xml';
    }

    private function exportCategory()
    {
        $element = Db::getInstance()->executeS("SELECT id_category, active, id_parent, `date_upd` FROM `" . _DB_PREFIX_ . "category` where id_category != " . (int) Configuration::get('PS_ROOT_CATEGORY') . " order by level_depth,id_category");

        $offset = (int) Configuration::get('PS_HOME_CATEGORY') - 1;

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('categories');
        foreach ($element as $i => $obj) {
            if (isset($obj['date_upd']))
                $data_upd = DateTime::createFromFormat('Y-m-d H:i:s', pSQL($obj['date_upd']))->format("d-m-Y H:i:s");
            else
                $data_upd = date("d-m-y H:i:s");
            $xml->startElement('category');
            $xml->writeAttribute('id_prestashop', (int)$obj['id_category'] - $offset);
            $xml->writeAttribute('id_parent_prestashop', (int)$obj['id_parent'] - $offset);
            $xml->writeAttribute('active', $obj['active']);
            $xml->writeAttribute('date_upd', $data_upd);

            foreach ($this->languages as $lang) {
                $xml->startElement("lang");
                $xml->writeAttribute('id', $lang['id_lang']);
                $category = new Category((int) $obj['id_category'], $lang['id_lang']);
                $xml->startElement("name");
                $xml->writeCdata($category->name);
                $xml->endElement();
                $xml->startElement("short");
                $xml->writeCdata($category->name);
                $xml->endElement();
                $xml->startElement("long");
                $xml->writeCdata($category->description);
                $xml->endElement();
                $xml->startElement("metatitle");
                $xml->writeCdata($category->meta_title);
                $xml->endElement();
                $xml->startElement("metakey");
                $xml->writeCdata($category->meta_keywords);
                $xml->endElement();
                $xml->startElement("metadescription");
                $xml->writeCdata($category->meta_description);
                $xml->endElement();
                $xml->endElement();
                //Image
                $xml->startElement("foto");
                if ($category->id_image) {
                    $xml->startElement('value');
                    $link = new Link();
                    $xml->writeAttribute('src', $link->getCatImageLink($category->name, (int) $category->id_image));
                    $xml->endElement();
                }
                $xml->endElement();
            }

            $xml->endElement();
            if (0 == $i % 1000) {
                file_put_contents(dirname(__FILE__) . '/export/category.xml', $xml->flush(true), FILE_APPEND);
            }
        }
        
        $xml->endElement();
        file_put_contents(dirname(__FILE__) . '/export/category.xml', $xml->flush(true), FILE_APPEND);
        return 'category.xml';
    }

    private function exportFeature()
    {
        $element = Feature::getFeatures($this->lang_default);
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('features');
        if(Feature::isFeatureActive()){
            foreach ($element as $i => $obj) {
                if (isset($obj['date_upd']))
                    $data_upd = DateTime::createFromFormat('Y-m-d H:i:s', pSQL($obj['date_upd']))->format("d-m-Y H:i:s");
                else
                    $data_upd = date("d-m-y H:i:s");
                $is_used = Db::getInstance()->getValue("SELECT COUNT(*) from `"._DB_PREFIX_."feature_product` where id_feature = ". (int)$obj['id_feature']);
                if(!$is_used){
                    continue;
                }
                $xml->startElement('feature');
                $xml->writeAttribute('id_prestashop', $obj['id_feature']);
                $xml->writeAttribute('date_upd', $data_upd);
                $xml->startElement('name');
                $xml->writeCdata($obj['name']);
                $xml->endElement();
                $xml->startElement("langs");
                foreach ($this->languages as $lang) {
                    $xml->startElement("lang");
                    $xml->writeAttribute('id', $lang['id_lang']);
                    $list = FeatureValue::getFeatureValuesWithLang($lang['id_lang'], $obj['id_feature'], true);
                    foreach ($list as $value) {
                        $xml->startElement("name");
                        $xml->writeAttribute('id_feature_value_prestashop', $value['id_feature_value']);
                        $xml->writeCdata($value['value']);
                        $xml->endElement();
                    }
                    $xml->endElement();
                }
                $xml->endElement();
                $xml->endElement();
                if (0 == $i % 1000) {
                    file_put_contents(dirname(__FILE__) . '/export/feature.xml', $xml->flush(true), FILE_APPEND);
                }
            }
        }
        $xml->endElement();
        file_put_contents(dirname(__FILE__) . '/export/feature.xml', $xml->flush(true), FILE_APPEND);
        return 'feature.xml';
    }

    private function exportProduct()
    {
        $element = Db::getInstance()->executeS("SELECT id_product FROM `" . _DB_PREFIX_ . "product`");

        $offset = (int) Configuration::get('PS_HOME_CATEGORY') - 1;
        
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('products');
        foreach ($element as $i => $obj) {
            $product = new Product((int) $obj['id_product'], false, (int) $this->lang_default);
            if (Validate::isLoadedObject($product)) {
                if (isset($product->date_upd))
                    $data_upd = DateTime::createFromFormat('Y-m-d H:i:s', pSQL($product->date_upd))->format("d-m-Y H:i:s");
                else
                    $data_upd = date("d-m-y H:i:s");
                $xml->startElement('product');
                // $xml->writeAttribute('id', '');
                $xml->writeAttribute('id_prestashop', $product->id);
                $xml->writeAttribute('home', (in_array(Configuration::get('PS_HOME_CATEGORY'), $product->getCategories()) ? '1' : '0'));
                $xml->writeAttribute('active', $product->active);
                $xml->writeAttribute('date_upd', $data_upd);

                //Product Name
                $xml->startElement("name");
                  $xml->writeCdata($product->name);
                $xml->endElement();

                /*
                 * DESUP
                 * $xml->startElement("dessup");
                 * $xml->writeCdata('');
                 * $xml->endElement();
                 *
                 * $xml->startElement("linkweb");
                 * $xml->writeCdata('');
                 * $xml->endElement();
                */
                $xml->writeElement("id_manufacturer", $product->id_manufacturer);

                $xml->writeElement("id_category_default", (int)$product->id_category_default - $offset);

                $xml->writeElement("id_tax_rules_group", $product->id_tax_rules_group);
                /* $xml->writeElement("imballo", 0);
                 * $xml->writeElement("strato", 0);
                 * $xml->writeElement("pedana", 0);
                 * $xml->startElement("catinv");
                 * $xml->writeCdata('');
                 * $xml->endElement();
                */
                $xml->writeElement("ean13", $product->ean13);
                $xml->startElement("upc");
                $xml->writeCdata($product->upc);
                $xml->endElement();
                // $xml->writeElement("dispo", 0);
                $xml->writeElement("quantity", StockAvailable::getQuantityAvailableByProduct($product->id));
                $xml->writeElement("minimal_quantity", $product->minimal_quantity);
                $xml->writeElement("price", str_replace(".", ",", $product->price));
                $xml->writeElement("price_ivato", str_replace(".", ",", $product->getPriceWithoutReduct()));
                $xml->startElement("reference");
                $xml->writeCdata($product->reference);
                $xml->endElement();
                $xml->startElement("ordinamento");
                $xml->writeCdata(0);
                $xml->endElement();
                $xml->startElement("supplier_reference");
                $xml->writeCdata($product->supplier_reference);
                $xml->endElement();
                $xml->writeElement("weight", str_replace(".", ",", $product->weight));
                $xml->writeElement("condition", $product->condition);
                $xml->startElement("langs");
                foreach ($this->languages as $lang) {
                    $prod_lang = new Product($product->id, false, (int) $lang['id_lang']);
                    $xml->startElement("lang");
                    $xml->writeAttribute('id', $lang['id_lang']);
                    $xml->startElement("description");
                    $xml->writeCdata($prod_lang->description);
                    $xml->endElement();
                    $xml->startElement("description_short");
                    $xml->writeCdata($product->description_short);
                    $xml->endElement();
                    $xml->startElement("name");
                    $xml->writeCdata($product->name);
                    $xml->endElement();
                    $xml->startElement("meta_description");
                    $xml->writeCdata($product->meta_description);
                    $xml->endElement();
                    $xml->startElement("meta_keywords");
                    $xml->writeCdata($product->meta_keywords);
                    $xml->endElement();
                    $xml->startElement("meta_title");
                    $xml->writeCdata($product->meta_title);
                    $xml->endElement();
                    $xml->startElement("available_now");
                    $xml->writeCdata($product->available_now);
                    $xml->endElement();
                    $xml->startElement("available_later");
                    $xml->writeCdata($product->available_later);
                    $xml->endElement();
                    $xml->endElement();
                }
                $xml->endElement();
                
                $categories = $product->getCategories();
                $xml->startElement("category");
                foreach ($categories as $cat) {
                    $xml->startElement('id_category');

                    $xml->writeAttribute('id', (int)$cat - $offset);

                    // $xml->writeAttribute('id_product', $product->id);
                    $xml->endElement();
                }
                $xml->endElement();

                // Combinations
                $var = $product->getAttributeCombinations((int) $this->lang_default);

                $combinations = array();
                foreach ($var as $i) {
                    $combinations[$i['id_product_attribute']]['quantity'] = $i['quantity'];
                    if (! in_array((int) $i['id_attribute_group'], $this->colori)){
                        $combinations[$i['id_product_attribute']]['attribute'][0] = $i['id_attribute'];
                        $combinations[$i['id_product_attribute']]['attribute']['taglia'] = $i['attribute_name'];
                    } else {
                        $combinations[$i['id_product_attribute']]['attribute'][1] = $i['id_attribute'];
                        $combinations[$i['id_product_attribute']]['attribute']['colore'] = $i['attribute_name'];
                    }
                    $combinations[$i['id_product_attribute']]['ean13'] = pSQL($i['ean13']);
                    $combinations[$i['id_product_attribute']]['price'] = pSQL($i['price']);
                    $combinations[$i['id_product_attribute']]['reference'] = pSQL($i['reference']);
                }
                $xml->startElement("combinations");
                foreach ($combinations as $combination) {
                    $xml->startElement('value');
                    $xml->writeAttribute('reference', $combination['reference']);
                    $xml->writeAttribute('EAN', $combination['ean13']);
                    $xml->writeAttribute('id_size', $combination['attribute'][0]);
                    $xml->writeAttribute('id_color', $combination['attribute'][1]);
                    $xml->writeAttribute('price', str_replace(".", ",", $combination['price']));
                    $xml->writeAttribute('quantity', $combination['quantity']);
                    $xml->writeAttribute('posfoto', '-');
                    $xml->writeAttribute('taglia', $combination['attribute']['taglia']);
                    $xml->writeAttribute('colore', $combination['attribute']['colore']);
                    $xml->endElement();
                }
                $xml->endElement();
                
                // Immagini
                $images = $product->getImages($this->lang_default);
                $xml->startElement("foto");
                foreach ($images as $img) {
                    $img_color = 0;
                    $img_color_query = "SELECT b.id_attribute FROM `" . _DB_PREFIX_ . "product_attribute_image` a 
                        JOIN `" . _DB_PREFIX_ . "product_attribute_combination` b ON a.`id_product_attribute` = b.`id_product_attribute`
                        JOIN `" . _DB_PREFIX_ . "attribute` c ON b.`id_attribute`= c.`id_attribute`
                        JOIN `" . _DB_PREFIX_ . "product_attribute` pa ON a.`id_product_attribute` = pa.`id_product_attribute`
                        WHERE a.id_image = ".(int) $img['id_image']." AND id_product = ".$product->id." AND c.id_attribute_group IN (".implode(',',$this->colori).") GROUP BY b.id_attribute";
                    if ($q_result = Db::getInstance()->getValue($img_color_query)) {
                        $img_color = (int)$q_result;
                    }
                    $xml->startElement('value');
                    $link = new Link();
                    $xml->writeAttribute('src', $link->getImageLink($product->link_rewrite, (int) $img['id_image']));
                    $xml->writeAttribute('posfoto', $img['position']);
                    $xml->writeAttribute('id_color',$img_color);
                    $xml->writeAttribute('alt', $img['legend']);
                    $xml->writeAttribute('title', $img['legend']);
                    $xml->endElement();
                }
                $xml->endElement();
                
                // feature
                $features = $product->getFeatures();
                $xml->startElement("feature");
                foreach ($features as $feat) {
                    $xml->startElement('id_feature');
                    $xml->writeAttribute('id', $feat['id_feature']);
                    $xml->writeAttribute('id_feature_value', $feat['id_feature_value']);
                    $xml->writeAttribute('id_product', $product->id);
                    $xml->writeAttribute('date_upd', $data_upd);
                    $xml->endElement();
                }
                $xml->endElement();
                
                // SpecificPrice
                $xml->startElement("specific_price");
                if (! is_null($specific = SpecificPrice::getIdsByProductId($product->id))) {
                    foreach ($specific as $val) {
                        $obj = new SpecificPrice((int) ($val['id_specific_price']));
                        $xml->startElement('value');
                        $xml->writeAttribute('Sconto', ($obj->reduction_type == 'percentage' ? 100 * (float) $obj->reduction : ""));
                        $xml->writeAttribute('Prezzo', str_replace(".", ",", $product->getPrice(false)));
                        $xml->writeAttribute('Ivato', str_replace(".", ",", $product->getPrice(true)));
                        $xml->writeAttribute('Qta', $obj->from_quantity);
                        $xml->writeAttribute('id_product', $product->id);
                        $xml->writeAttribute('datini', $obj->from);
                        $xml->writeAttribute('datfin', $obj->to);
                        $xml->endElement();
                    }
                }
                $xml->endElement();
                $xml->endElement();
            }
            if (0 == $i % 1000) {
                file_put_contents(dirname(__FILE__) . '/export/product.xml', $xml->flush(true), FILE_APPEND);
            }
        }
        
        $xml->endElement();
        file_put_contents(dirname(__FILE__) . '/export/product.xml', $xml->flush(true), FILE_APPEND);
        return 'product.xml';
    }

    private function exportCustomer()
    {
        $element = Customer::getCustomers(true);
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('customers');
        foreach ($element as $i => $obj) {
            $customer = new Customer($obj['id_customer']);
            $xml->startElement('customer');
            $xml->writeAttribute('id', $customer->id);
            $xml->startElement("firstname");
            $xml->writeCdata($customer->firstname);
            $xml->endElement();
            $xml->startElement("lastname");
            $xml->writeCdata($customer->lastname);
            $xml->endElement();
            $xml->writeElement("email", $customer->email);
            $xml->startElement("addresses");
            foreach ($customer->getAddresses($this->lang_default) as $value) {
                $xml->startElement("address");
                $xml->writeAttribute('id', $value['id_address']);
                $xml->writeElement('country', $value['country']);
                $xml->writeElement('state', $value['state']);
                $xml->writeElement('city', $value['city']);
                $xml->writeElement('postcode', $value['postcode']);
                $xml->writeElement('alias', $value['alias']);
                $xml->writeElement('company', $value['company']);
                $xml->writeElement('firstname', $value['firstname']);
                $xml->writeElement('lastname', $value['lastname']);
                $xml->writeElement('address1', $value['address1']);
                $xml->writeElement('address2', $value['address2']);
                $xml->writeElement('vat_number', $value['vat_number']);
                $xml->writeElement('phone', $value['phone']);
                $xml->writeElement('phone_mobile', $value['phone_mobile']);
                $xml->writeElement('dni', $value['dni']);
                $xml->endElement();
            }
            $xml->endElement();
            $xml->endElement();
            if (0 == $i % 1000) {
                file_put_contents(dirname(__FILE__) . '/export/customer.xml', $xml->flush(true), FILE_APPEND);
            }
        }
        
        $xml->endElement();
        file_put_contents(dirname(__FILE__) . '/export/customer.xml', $xml->flush(true), FILE_APPEND);
        return 'customer.xml';
    }

    public function ajaxProcessExport()
    {
        $call = Tools::getValue('call','0');
        $return = '';
        switch ($call) {
            case 'manufacturer':
                $return = $this->exportManufacturer();
                break;
            case 'attribute':
                $return = $this->exportAttribute();
                break;
            case 'category':
                $return = $this->exportCategory();
                break;
            case 'feature':
                $return = $this->exportFeature();
                break;
            case 'customer':
                $return = $this->exportCustomer();
                break;
            case 'product':
                $return = $this->exportProduct();
                break;
            case 'close':
                $return = $this->export();
                break;    
        }
        header('Content-Type: application/json');
        die(Tools::jsonEncode($return));
    }

    public function export()
    {
        @unlink(dirname(__FILE__) . '/export/' . Tools::strtolower($this->name) . '.zip');
        $filestozip = Tools::scandir(dirname(__FILE__) . '/export/','xml');
        $zipname = Tools::strtolower($this->name) . '.zip';
        $zip = new ZipArchive();
        $zip->open(dirname(__FILE__) . '/export/' . $zipname, ZipArchive::CREATE);
        foreach ($filestozip as $file) {
            if (file_exists(dirname(__FILE__) . '/export/' . $file))
                $zip->addFile(dirname(__FILE__) . '/export/' . $file, $file);
        }
        if(file_exists(_PS_ROOT_DIR_ . '/app/config/parameters.php')){
            $zip->addFile(_PS_ROOT_DIR_ . '/app/config/parameters.php', 'parameters.php');

        } else if(file_exists(_PS_ROOT_DIR_ . '/config/setting.incs.php')){
            $zip->addFile(_PS_ROOT_DIR_ . '/config/setting.incs.php', 'settings.inc.php');
        } 

        if ($zip->close()) {
            foreach ($filestozip as $file) {
                unlink(dirname(__FILE__) . '/export/' . $file);
            }
        }
    }
}
    