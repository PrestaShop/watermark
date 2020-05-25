<?php
namespace PrestaShop\Module\Watermark\Htaccess;

use Tools;
use Symfony\Component\Translation\TranslatorInterface as Translator;

class HtaccessManager
{

    private $translator;

    /**

     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @return bool|string
     */
    public function getAdminDir()
    {

        $adminDir = str_replace('\\', '/', _PS_ADMIN_DIR_);
        $adminDir = explode('/', $adminDir);
        $len = count($adminDir);

        return $len > 1 ? $adminDir[$len - 1] : _PS_ADMIN_DIR_;
    }

    /**
     * Write the watermark section to the .htaccess file
     *
     * @return boolean
     */
    public function writeHtaccessSection()
    {
        //var_dump($this);die;
        $adminDir = $this->getAdminDir();
        $source = "\n# start ~ module watermark section
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond expr \"! %{HTTP_REFERER} -strmatch '*://%{HTTP_HOST}*/$adminDir/*'\"
RewriteRule [0-9/]+/[0-9]+\\.jpg$ - [F]
</IfModule>
# end ~ module watermark section\n";

        $path = _PS_ROOT_DIR_ . '/.htaccess';
        if (false === file_put_contents($path, $source . file_get_contents($path))) {
            $this->context->controller->errors[] = $this->translator->trans('Unable to add watermark section to the .htaccess file', [], 'Modules.Watermark.Admin');

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function removeHtaccessSection()
    {
        $key1 = "\n# start ~ module watermark section";
        $key2 = "# end ~ module watermark section\n";
        $path = _PS_ROOT_DIR_ . '/.htaccess';
        if (file_exists($path) && is_writable($path)) {
            $s = Tools::file_get_contents($path);
            $p1 = strpos($s, $key1);
            $p2 = strpos($s, $key2, $p1);
            if ($p1 === false || $p2 === false) {
                return false;
            }
            $s = Tools::substr($s, 0, $p1) . Tools::substr($s, $p2 + Tools::strlen($key2));
            file_put_contents($path, $s);
        }

        return true;
    }
}
