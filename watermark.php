<?php
/**
 * 2007-2018 PrestaShop
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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class Watermark
 */
class Watermark extends Module
{
    /** @var array $_postErrors */
    protected $_postErrors = [];
    /** @var array $xaligns */
    protected $xaligns = ['left', 'middle', 'right'];
    /** @var array $yaligns */
    protected $yaligns = ['top', 'middle', 'bottom'];
    /** @var string $yAlign */
    protected $yAlign;
    /** @var string $xAlign */
    protected $xAlign;
    /** @var int $transparency */
    protected $transparency;
    /** @var array $imageTypes */
    protected $imageTypes = [];

    /**
     * Watermark constructor.
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'watermark';
        $this->tab = 'administration';
        $this->version = '1.2.0';
        $this->author = 'PrestaShop';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Watermark', [], 'Modules.Watermark.Admin');
        $this->description = $this->trans('Protect image by watermark.', [], 'Modules.Watermark.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to delete your details?', [], 'Modules.Watermark.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.4.0', 'max' => _PS_VERSION_];

        $config = Configuration::getMultiple(
            [
                'WATERMARK_TYPES',
                'WATERMARK_Y_ALIGN',
                'WATERMARK_X_ALIGN',
                'WATERMARK_TRANSPARENCY',
                'WATERMARK_LOGGED',
                'WATERMARK_HASH',
            ]
        );
        if (!isset($config['WATERMARK_TYPES'])) {
            $config['WATERMARK_TYPES'] = '';
        }
        $tmp = explode(',', $config['WATERMARK_TYPES']);
        foreach (ImageType::getImagesTypes('products') as $type) {
            if (in_array($type['id_image_type'], $tmp)) {
                $this->imageTypes[] = $type;
            }
        }

        $this->yAlign = isset($config['WATERMARK_Y_ALIGN']) ? $config['WATERMARK_Y_ALIGN'] : '';
        $this->xAlign = isset($config['WATERMARK_X_ALIGN']) ? $config['WATERMARK_X_ALIGN'] : '';
        $this->transparency = isset($config['WATERMARK_TRANSPARENCY']) ? $config['WATERMARK_TRANSPARENCY'] : 60;

        if (!isset($config['WATERMARK_HASH'])) {
            Configuration::updateValue('WATERMARK_HASH', Tools::passwdGen(10));
        }

        if (!isset($this->transparency) || !isset($this->xAlign) || !isset($this->yAlign)) {
            $this->warning = $this->trans('Watermark image must be uploaded for this module to work correctly.', [], 'Modules.Watermark.Admin');
        }
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function install()
    {
        $this->writeHtaccessSection();
        if (!parent::install() || !$this->registerHook('watermark')) {
            return false;
        }
        Configuration::updateValue('WATERMARK_TRANSPARENCY', 60);
        Configuration::updateValue('WATERMARK_Y_ALIGN', 'bottom');
        Configuration::updateValue('WATERMARK_X_ALIGN', 'right');

        return true;
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     */
    public function uninstall()
    {
        if (!$this->removeHtaccessSection()) {
            $this->context->controller->errors[] = $this->trans('Unable to remove watermark section from .htaccess file', [], 'Module.Watermark.Admin');
        }

        return (parent::uninstall()
            && Configuration::deleteByName('WATERMARK_TYPES')
            && Configuration::deleteByName('WATERMARK_TRANSPARENCY')
            && Configuration::deleteByName('WATERMARK_Y_ALIGN')
            && Configuration::deleteByName('WATERMARK_LOGGED')
            && Configuration::deleteByName('WATERMARK_X_ALIGN'));
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     */
    private function _postValidation()
    {
        $yalign = Tools::getValue('yalign');
        $xalign = Tools::getValue('xalign');
        $transparency = (int) (Tools::getValue('transparency'));

        $types = ImageType::getImagesTypes('products');
        $id_image_type = [];
        foreach ($types as $type) {
            if (!is_null(Tools::getValue('WATERMARK_TYPES_'.(int) $type['id_image_type']))) {
                $id_image_type['WATERMARK_TYPES_'.(int) $type['id_image_type']] = true;
            }
        }

        if (empty($transparency)) {
            $this->_postErrors[] = $this->trans('Opacity required.', [], 'Modules.Watermark.Admin');
        } elseif ($transparency < 1 || $transparency > 100) {
            $this->_postErrors[] = $this->trans('Opacity is not in allowed range.', [], 'Modules.Watermark.Admin');
        }

        if (empty($yalign)) {
            $this->_postErrors[] = $this->trans('Y-Align is required.', [], 'Modules.Watermark.Admin');
        } elseif (!in_array($yalign, $this->yaligns)) {
            $this->_postErrors[] = $this->trans('Y-Align is not in allowed range.', [], 'Modules.Watermark.Admin');
        }

        if (empty($xalign)) {
            $this->_postErrors[] = $this->trans('X-Align is required.', [], 'Modules.Watermark.Admin');
        } elseif (!in_array($xalign, $this->xaligns)) {
            $this->_postErrors[] = $this->trans('X-Align is not in allowed range.', [], 'Modules.Watermark.Admin');
        }
        if (!count($id_image_type)) {
            $this->_postErrors[] = $this->trans('At least one image type is required.', [], 'Modules.Watermark.Admin');
        }

        if (isset($_FILES['PS_WATERMARK']['tmp_name']) && !empty($_FILES['PS_WATERMARK']['tmp_name'])) {
            if (!ImageManager::isRealImage($_FILES['PS_WATERMARK']['tmp_name'], $_FILES['PS_WATERMARK']['type'], ['image/gif', 'image/jpeg', 'image/jpg', 'image/png'])) {
                $this->_postErrors[] = $this->trans('Image must be in JPEG, PNG or GIF format.', [], 'Modules.Watermark.Admin');
            }
        }

        return !count($this->_postErrors) ? true : false;
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopExceptionCore
     */
    private function _postProcess()
    {
        $types = ImageType::getImagesTypes('products');
        $id_image_type = [];
        foreach ($types as $type) {
            if (Tools::getValue('WATERMARK_TYPES_'.(int) $type['id_image_type'])) {
                $id_image_type[] = $type['id_image_type'];
            }
        }

        Configuration::updateValue('WATERMARK_TYPES', implode(',', $id_image_type));
        Configuration::updateValue('WATERMARK_Y_ALIGN', Tools::getValue('yalign'));
        Configuration::updateValue('WATERMARK_X_ALIGN', Tools::getValue('xalign'));
        Configuration::updateValue('WATERMARK_TRANSPARENCY', Tools::getValue('transparency'));
        Configuration::updateValue('WATERMARK_LOGGED', Tools::getValue('WATERMARK_LOGGED'));

        if (Shop::getContext() == Shop::CONTEXT_SHOP) {
            $strShop = '-'.(int) $this->context->shop->id;
        } else {
            $strShop = '';
        }

        // Submitted watermark
        if (isset($_FILES['PS_WATERMARK']) && !empty($_FILES['PS_WATERMARK']['tmp_name'])) {
            /* Check watermark validity */
            if ($error = ImageManager::validateUpload($_FILES['PS_WATERMARK'])) {
                $this->_errors[] = $error;
            } /* Copy new watermark */
            elseif (!@copy($_FILES['PS_WATERMARK']['tmp_name'], dirname(__FILE__).'/'.$this->name.$strShop.'.'.static::getWatermarkExtension($strShop))) {
                $this->_errors[] = sprintf($this->trans('An error occurred while uploading watermark: %1$s to %2$s', [], 'Modules.Watermark.Admin'),
                    $_FILES['PS_WATERMARK']['tmp_name'],
                    dirname(__FILE__).'/'.$this->name.$strShop.'.'.static::getWatermarkExtension($strShop)
                ).' ['.error_get_last()['message'].']';
            }
        }

        if ($this->_errors) {
            foreach ($this->_errors as $error) {
                $this->context->controller->errors[] = $this->trans($error, [], 'Modules.Watermark.Admin');
            }
        } else {
            Tools::redirectAdmin('index.php?tab=AdminModules&configure='.$this->name.'&conf=6&token='.Tools::getAdminTokenLite('AdminModules'));
        }
    }

    /**
     * @return bool|string
     */
    public function getAdminDir()
    {
        $admin_dir = str_replace('\\', '/', _PS_ADMIN_DIR_);
        $admin_dir = explode('/', $admin_dir);
        $len = count($admin_dir);

        return $len > 1 ? $admin_dir[$len - 1] : _PS_ADMIN_DIR_;
    }

    /**
     * @return bool
     */
    public function removeHtaccessSection()
    {
        $key1 = "\n# start ~ module watermark section";
        $key2 = "# end ~ module watermark section\n";
        $path = _PS_ROOT_DIR_.'/.htaccess';
        if (file_exists($path) && is_writable($path)) {
            $s = file_get_contents($path);
            $p1 = strpos($s, $key1);
            $p2 = strpos($s, $key2, $p1);
            if ($p1 === false || $p2 === false) {
                return false;
            }
            $s = substr($s, 0, $p1).substr($s, $p2 + strlen($key2));
            file_put_contents($path, $s);
        }

        return true;
    }

    /**
     * Write the watermark section to the .htaccess file
     *
     * @return boolean
     */
    public function writeHtaccessSection()
    {
        $adminDir = $this->getAdminDir();
        $source = "\n# start ~ module watermark section
<IfModule mod_rewrite.c>
Options +FollowSymLinks
RewriteEngine On
RewriteCond expr \"! %{HTTP_REFERER} -strmatch '*://%{HTTP_HOST}*/$adminDir/*'\"
RewriteRule [0-9/]+/[0-9]+\\.jpg$ - [F]
</IfModule>
# end ~ module watermark section\n";

        $path = _PS_ROOT_DIR_.'/.htaccess';
        if (false === file_put_contents($path, $source, FILE_APPEND)) {
            $this->context->controller->errors[] = $this->trans('Unable to add watermark section to the .htaccess file', [], 'Modules.Watermark.Admin');
            return false;
        }

        return true;
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws PrestaShopExceptionCore
     */
    public function getContent()
    {
        // Modify htaccess to prevent download of original pictures
        $this->removeHtaccessSection();
        $this->writeHtaccessSection();

        $html = '';
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $html .= $this->displayError($err);
                }
            }
        }

        $html .= $this->renderForm();

        return $html;
    }

    /**
     * Retrocompatibility
     *
     * @param array $params
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookwatermark($params)
    {
        $this->hookActionWatermark($params);
    }

    /**
     * @param array $params
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionWatermark($params)
    {
        $image = new Image($params['id_image']);
        $image->id_product = $params['id_product'];
        $file = _PS_PROD_IMG_DIR_.$image->getExistingImgPath().'-watermark.jpg';
        $fileOrg = _PS_PROD_IMG_DIR_.$image->getExistingImgPath().'.jpg';

        $strShop = '-'.(int) $this->context->shop->id;
        if (Shop::getContext() != Shop::CONTEXT_SHOP || !Tools::file_exists_cache(dirname(__FILE__).'/'.$this->name.$strShop.'.'.static::getWatermarkExtension($strShop))) {
            $strShop = '';
        }

        //first make a watermark image
        $return = $this->watermarkByImage(
            _PS_PROD_IMG_DIR_.$image->getExistingImgPath().'.jpg',
            __DIR__.'/'.$this->name.$strShop.'.'.static::getWatermarkExtension($strShop),
            $file,
            23,
            0,
            0,
            'right'
        );

        if (!Configuration::get('WATERMARK_HASH')) {
            Configuration::updateValue('WATERMARK_HASH', Tools::passwdGen(10));
        }

        if (isset($params['image_type']) && is_array($params['image_type'])) {
            $this->imageTypes = array_intersect($this->imageTypes, $params['image_type']);
        }

        //go through file formats defined for watermark and resize them
        foreach ($this->imageTypes as $imageType) {
            $newFile = _PS_PROD_IMG_DIR_.$image->getExistingImgPath().'-'.stripslashes($imageType['name']).'.jpg';
            if (!ImageManager::resize($file, $newFile, (int) $imageType['width'], (int) $imageType['height'])) {
                $return = false;
            }

            $newFileOrg = _PS_PROD_IMG_DIR_.$image->getExistingImgPath().'-'.stripslashes($imageType['name']).'-'.Configuration::get('WATERMARK_HASH').'.jpg';
            if (!ImageManager::resize($fileOrg, $newFileOrg, (int) $imageType['width'], (int) $imageType['height'])) {
                $return = false;
            }
        }

        return $return;
    }

    /**
     * @param string $imagepath
     * @param string $watermarkpath
     * @param string $outputpath
     *
     * @return bool
     */
    private function watermarkByImage($imagepath, $watermarkpath, $outputpath)
    {
        $Xoffset = $Yoffset = $xpos = $ypos = 0;

        // Force image type by extension
        $watermarkExtension = Tools::strtolower(pathinfo($watermarkpath, PATHINFO_EXTENSION));
        if ($watermarkExtension === 'jpeg') {
            $watermarkExtension = 'jpg';
        }

        list(, , $type) = getimagesize($imagepath);
        $image = ImageManager::create($type, $imagepath);
        $imagew = ImageManager::create(static::convertExtensionToImageType($watermarkExtension), $watermarkpath);
        if (!$imagew) {
            $this->context->controller->errors[] = $this->trans('The watermark image could not be loaded, please CONVERT the image.', [], 'Modules.Watermark.Admin');
            return false;
        }
        if (!$image) {
            return false;
        }

        list($watermarkWidth, $watermarkHeight) = getimagesize($watermarkpath);
        list($imageWidth, $imageHeight) = getimagesize($imagepath);
        if ($this->xAlign == 'middle') {
            $xpos = $imageWidth / 2 - $watermarkWidth / 2 + $Xoffset;
        }
        if ($this->xAlign == 'left') {
            $xpos = 0 + $Xoffset;
        }
        if ($this->xAlign == 'right') {
            $xpos = $imageWidth - $watermarkWidth - $Xoffset;
        }
        if ($this->yAlign == 'middle') {
            $ypos = $imageHeight / 2 - $watermarkHeight / 2 + $Yoffset;
        }
        if ($this->yAlign == 'top') {
            $ypos = 0 + $Yoffset;
        }
        if ($this->yAlign == 'bottom') {
            $ypos = $imageHeight - $watermarkHeight - $Yoffset;
        }
        if (!imagecopymerge($image, $imagew, $xpos, $ypos, 0, 0, $watermarkWidth, $watermarkHeight, $this->transparency)) {
            return false;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return ImageManager::write($type, $image, $outputpath);
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function renderForm()
    {
        $types = ImageType::getImagesTypes('products');
        foreach ($types as $key => $type) {
            $types[$key]['label'] = $type['name'].' ('.$type['width'].' x '.$type['height'].')';
        }

        if (Shop::getContext() == Shop::CONTEXT_SHOP) {
            $strShop = '-'.(int) $this->context->shop->id;
        } else {
            $strShop = '';
        }
        $imageExt = static::getWatermarkExtension($strShop);

        $fields_form = [
            'form' => [
                'legend'      => [
                    'title' => $this->trans('Settings', [], 'Modules.Watermark.Admin'),
                    'icon'  => 'icon-cogs',
                ],
                'description' => $this->trans('Once you have set up the module, regenerate the images using the "Images" tool in Preferences. However, the watermark will be added automatically to new images.', [], 'Modules.Watermark.Admin'),
                'input'       => [
                    [
                        'type'  => 'file',
                        'label' => $this->trans('Watermark file:', [], 'Modules.Watermark.Admin'),
                        'name'  => 'PS_WATERMARK',
                        'desc'  => $this->trans('Must be in JPEG, PNG or GIF format', [], 'Modules.Watermark.Admin'),
                        'thumb' => "{$this->_path}{$this->name}{$strShop}.{$imageExt}?t=".rand(0, time()),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->trans('Watermark opacity (1-100)', [], 'Modules.Watermark.Admin'),
                        'name'  => 'transparency',
                        'class' => 'fixed-width-md',
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->trans('Watermark X align:', [], 'Modules.Watermark.Admin'),
                        'name'    => 'xalign',
                        'class'   => 'fixed-width-md',
                        'options' => [
                            'query' => [
                                [
                                    'id'   => 'left',
                                    'name' => $this->trans('left', [], 'Modules.Watermark.Admin'),
                                ],
                                [
                                    'id'   => 'middle',
                                    'name' => $this->trans('middle', [], 'Modules.Watermark.Admin'),
                                ],
                                [
                                    'id'   => 'right',
                                    'name' => $this->trans('right', [], 'Modules.Watermark.Admin'),
                                ],
                            ],
                            'id'    => 'id',
                            'name'  => 'name',
                        ],
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->trans('Watermark Y align:', [], 'Modules.Watermark.Admin'),
                        'name'    => 'yalign',
                        'class'   => 'fixed-width-md',
                        'options' => [
                            'query' => [
                                [
                                    'id'   => 'top',
                                    'name' => $this->trans('top', [], 'Modules.Watermark.Admin'),
                                ],
                                [
                                    'id'   => 'middle',
                                    'name' => $this->trans('middle', [], 'Modules.Watermark.Admin'),
                                ],
                                [
                                    'id'   => 'bottom',
                                    'name' => $this->trans('bottom', [], 'Modules.Watermark.Admin'),
                                ],
                            ],
                            'id'    => 'id',
                            'name'  => 'name',
                        ],
                    ],
                    [
                        'type'   => 'checkbox',
                        'name'   => 'WATERMARK_TYPES',
                        'label'  => $this->trans('Choose image types for watermark protection:', [], 'Modules.Watermark.Admin'),
                        'values' => [
                            'query' => $types,
                            'id'    => 'id_image_type',
                            'name'  => 'label',
                        ],
                    ],
                    [
                        'type'    => "switch",
                        'name'    => 'WATERMARK_LOGGED',
                        'label'   => $this->trans('Logged in customers see images without watermark', [], 'Modules.Watermark.Admin'),
                        'is_bool' => true,
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', [], 'Modules.Watermark.Admin'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', [], 'Modules.Watermark.Admin'),
                            ],
                        ],
                    ],
                ],
                'submit'      => [
                    'title' => $this->trans('Save', [], 'Modules.Watermark.Admin'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    /**
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public function getConfigFieldsValues()
    {
        $config_fields = [
            'PS_WATERMARK'     => Tools::getValue('PS_WATERMARK', Configuration::get('PS_WATERMARK')),
            'transparency'     => Tools::getValue('transparency', Configuration::get('WATERMARK_TRANSPARENCY')),
            'xalign'           => Tools::getValue('xalign', Configuration::get('WATERMARK_X_ALIGN')),
            'yalign'           => Tools::getValue('yalign', Configuration::get('WATERMARK_Y_ALIGN')),
            'WATERMARK_LOGGED' => Tools::getValue('WATERMARK_LOGGED', Configuration::get('WATERMARK_LOGGED')),
        ];
        //get all images type available
        $types = ImageType::getImagesTypes('products');
        $id_image_type = [];
        foreach ($types as $type) {
            $id_image_type[] = $type['id_image_type'];
        }

        //get images type from $_POST
        $id_image_type_post = [];
        foreach ($id_image_type as $id) {
            if (Tools::getValue('WATERMARK_TYPES_'.(int) $id)) {
                $id_image_type_post['WATERMARK_TYPES_'.(int) $id] = true;
            }
        }

        //get images type from Configuration
        $id_image_type_config = [];
        if ($confs = Configuration::get('WATERMARK_TYPES')) {
            $confs = explode(',', Configuration::get('WATERMARK_TYPES'));
        } else {
            $confs = [];
        }

        foreach ($confs as $conf) {
            $id_image_type_config['WATERMARK_TYPES_'.(int) $conf] = true;
        }

        //return only common values and value from post
        if (Tools::isSubmit('btnSubmit')) {
            $config_fields = array_merge($config_fields, array_intersect($id_image_type_post, $id_image_type_config));
        } else {
            $config_fields = array_merge($config_fields, $id_image_type_config);
        }

        return $config_fields;
    }

    /**
     * Get the watermark icon extension, by testing its existence
     *
     * @param string $strShop Shop part of the filename
     *
     * @since 1.2.0
     * @return string
     */
    public static function getWatermarkExtension($strShop)
    {
        // No need to probe the filesystem every single time
        static $cache = [];
        if (array_key_exists($strShop, $cache)) {
            return $cache[$strShop];
        }

        // Probe image file
        $imageExt = 'gif';
        if (file_exists(__DIR__."/watermark{$strShop}.png")) {
            $imageExt = 'png';
        } elseif (file_exists(__DIR__."/watermark{$strShop}.jpg")) {
            $imageExt = 'jpg';
        }
        $cache[$strShop] = $imageExt;

        return $imageExt;
    }

    /**
     * Convert file extension to image type
     *
     * @param string $extension
     *
     * @return int
     *
     * @since 1.2.0
     */
    public static function convertExtensionToImageType($extension)
    {
        $imageTypes = [
            'jpg'  => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG,
            'gif'  => IMAGETYPE_GIF,
            'png'  => IMAGETYPE_PNG,
        ];

        if (!array_key_exists(Tools::strtolower($extension), $imageTypes)) {
            return IMAGETYPE_GIF;
        }

        return $imageTypes[Tools::strtolower($extension)];
    }
}
