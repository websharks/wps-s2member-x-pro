<?php
/**
 * Checkout utilities.
 *
 * @author @jaswsinc
 * @copyright WebSharks™
 */
declare(strict_types=1);
namespace WebSharks\WpSharks\WooCommerce\Restrictions\Pro\Classes\Utils;

use WebSharks\WpSharks\WooCommerce\Restrictions\Pro\Classes;
use WebSharks\WpSharks\WooCommerce\Restrictions\Pro\Interfaces;
use WebSharks\WpSharks\WooCommerce\Restrictions\Pro\Traits;
#
use WebSharks\WpSharks\WooCommerce\Restrictions\Pro\Classes\AppFacades as a;
use WebSharks\WpSharks\WooCommerce\Restrictions\Pro\Classes\SCoreFacades as s;
use WebSharks\WpSharks\WooCommerce\Restrictions\Pro\Classes\CoreFacades as c;
#
use WebSharks\WpSharks\Core\Classes as SCoreClasses;
use WebSharks\WpSharks\Core\Interfaces as SCoreInterfaces;
use WebSharks\WpSharks\Core\Traits as SCoreTraits;
#
use WebSharks\Core\WpSharksCore\Classes as CoreClasses;
use WebSharks\Core\WpSharksCore\Classes\Core\Base\Exception;
use WebSharks\Core\WpSharksCore\Interfaces as CoreInterfaces;
use WebSharks\Core\WpSharksCore\Traits as CoreTraits;
#
use function assert as debug;
use function get_defined_vars as vars;

/**
 * Checkout utilities.
 *
 * @since 160524 Order-related events.
 */
class Checkout extends SCoreClasses\SCore\Base\Core
{
    /**
     * Original settings that need reversion.
     *
     * @since 160524 Checkout utilities.
     *
     * @param array Original settings.
     */
    protected $original_settings;

    /**
     * Class constructor.
     *
     * @since 160524 Checkout utilities.
     *
     * @param Classes\App $App Instance.
     */
    public function __construct(Classes\App $App)
    {
        parent::__construct($App);

        $this->original_settings = []; // Initialize.
    }

    /**
     * Cart contains permissions?
     *
     * @since 160524 Checkout utilities.
     *
     * @return bool True if cart contains permissions.
     */
    protected function cartContainsPermissions()
    {
        if (($contains = &$this->cacheKey(__FUNCTION__)) !== null) {
            return $contains; // Already cached this.
        }
        $WooCommerce = WC(); // `\WooCommerce` instance.

        if ($WooCommerce->cart->is_empty()) {
            return $contains = false;
        }
        foreach ($WooCommerce->cart->get_cart() as $_cart_item) {
            if (!($_WC_Product = $_cart_item['data'])) {
                debug(0, c::issue(vars(), 'Empty item data.'));
                continue; // Not possible.
            } elseif (!($_WC_Product instanceof \WC_Product)) {
                debug(0, c::issue(vars(), 'Unexpected data.'));
                continue; // Not possible.
            } elseif (!$_WC_Product->exists() || !($_product_id = $_WC_Product->get_id())) {
                debug(0, c::issue(vars(), 'Missing product ID.'));
                continue; // Not possible.
            }
            if (s::collectPostMeta($_product_id, '_permissions')) {
                return $contains = true;
            }
        } // unset($_cart_item, $_WC_Product, $_product_id);

        return $contains = false;
    }

    /**
     * Before checkout form.
     *
     * @since 160524 Checkout utilities.
     *
     * @param \WC_Checkout $WC_Checkout Class instance.
     */
    public function onBeforeCheckoutForm(\WC_Checkout $WC_Checkout)
    {
        if (!$this->cartContainsPermissions()) {
            return; // Not applicable.
        }
        $this->original_settings['enable_signup']         = $WC_Checkout->enable_signup;
        $this->original_settings['enable_guest_checkout'] = $WC_Checkout->enable_guest_checkout;
        $this->original_settings['must_create_account']   = $WC_Checkout->must_create_account;

        $WC_Checkout->enable_signup         = true;
        $WC_Checkout->enable_guest_checkout = false;
        $WC_Checkout->must_create_account   = !is_user_logged_in();
    }

    /**
     * Filter checkout fields.
     *
     * @since 160524 Checkout utilities.
     *
     * @param array $fields Checkout fields.
     *
     * @return array Checkout fields.
     */
    public function onCheckoutFields(array $fields): array
    {
        if (!$this->cartContainsPermissions()) {
            return $fields; // Not applicable.
        }
        foreach (['account_username', 'account_password', 'account_password-2'] as $_account_field) {
            if (isset($fields['account'][$_account_field])) {
                $fields['account'][$_account_field]['required'] = true;
            }
        } // unset($_account_field); // Housekeeping.

        return $fields;
    }

    /**
     * Filter JS checkout params.
     *
     * @since 160524 Checkout utilities.
     *
     * @param array $params JS checkout params.
     *
     * @return array JS checkout params.
     */
    public function onCheckoutParams(array $params): array
    {
        if (!$this->cartContainsPermissions()) {
            return $params; // Not applicable.
        }
        if (isset($params['option_guest_checkout'])) {
            $params['option_guest_checkout'] = 'no';
        }
        return $params;
    }

    /**
     * After checkout form.
     *
     * @since 160524 Checkout utilities.
     *
     * @param \WC_Checkout $WC_Checkout Class instance.
     */
    public function onAfterCheckoutForm(\WC_Checkout $WC_Checkout)
    {
        if (!$this->cartContainsPermissions()) {
            return; // Not applicable.
        }
        foreach ($this->original_settings as $_setting => $_value) {
            if ($_setting && isset($_value)) { // Restore.
                $WC_Checkout->{$_setting} = $_value;
            }
        } // unset($_setting, $_value); // Housekeeping.
    }

    /**
     * Before processing checkout.
     *
     * @since 160524 Checkout utilities.
     */
    public function onBeforeCheckoutProcess()
    {
        if (!$this->cartContainsPermissions()) {
            return; // Not applicable.
        }
        if (!empty($_POST) && !is_user_logged_in()) {
            $_POST['createaccount'] = 1;
        }
    }
}
