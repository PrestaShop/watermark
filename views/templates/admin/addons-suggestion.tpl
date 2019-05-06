{**
* 2007-2019 PrestaShop and Contributors
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License 3.0 (AFL-3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* https://opensource.org/licenses/AFL-3.0
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to https://www.prestashop.com for more information.
*
* @author    PrestaShop SA <contact@prestashop.com>
* @copyright 2007-2019 PrestaShop SA and Contributors
* @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
* International Registered Trademark & Property of PrestaShop SA
*}

<script type="text/javascript">
function showHideText() {
    var moreDots = document.getElementById("suggestion-wording-dots");
    var moreText = document.getElementById("suggestion-wording-more-text");
    var moreLabel= document.getElementById("suggestion-wording-more-label");

    if (moreDots.style.display === "none") {
        moreDots.style.display = "inline";
        moreLabel.innerHTML = "{l s='Read more' d='Admin.Actions'}";
        moreText.style.display = "none";
    } else {
        moreDots.style.display = "none";
        moreLabel.innerHTML = "{l s='See less' d='Admin.Actions'}";
        moreText.style.display = "inline";
    }
}
</script>

<div class="module-addons-suggestion">
    <div class="suggestion-icon">
    </div>
    <div class="suggestion-category-details">
        <div>
            {l s='To go further:' d='Admin.Modules.Feature'}
        </div>
        <div class="category-label">
            {l s='Labels, Stickers & Logos' d='Admin.Modules.Feature'}
        </div>
        <div class="marketplace-label">
            {l s='Addons Marketplace' d='Admin.Modules.Feature'}
        </div>
    </div>
    <div class="suggestion-wording">
        {$suggestionWording|unescape:'html'} <a href="#void" id="suggestion-wording-more-label" onclick="showHideText();">{l s='Read more' d='Admin.Actions'}</a>
    </div>
    <div class="suggestion-link">
        <a target="_blank" class="btn btn-primary"  href="{$addons_watermark_link}">
            {l s='Discover all modules' d='Admin.Modules.Feature'}
        </a>
    </div>
</div>