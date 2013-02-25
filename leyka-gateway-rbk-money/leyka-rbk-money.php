<?php
/*
Plugin Name: Leyka RBK Money gateway
Plugin URI: http://leyka.te-st.ru/
Description: Gateway for Leyka donations management system which adds option for receiving donates using RBK Money payment service. Can only be used for receiving donations!
Version: 1.0
Author: Lev Zvyagincev aka Ahaenor
Author URI: ahaenor@gmail.com
License: GPLv2 or later

	Copyright (C) 2012-2013 by Teplitsa of Social Technologies (http://te-st.ru).

	GNU General Public License, Free Software Foundation <http://www.gnu.org/licenses/gpl-2.0.html>

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

function leyka_rbk_money_plugins_loaded(){
    // Set filter for plugin's languages directory
    $plugin_lang_dir = dirname(plugin_basename(__FILE__)).'/languages/';
    $plugin_lang_dir = apply_filters('leyka_languages_directory', $plugin_lang_dir);

    // Traditional WordPress plugin locale filter
    $locale = apply_filters('plugin_locale', get_locale(), 'leyka-rbk-money');
    $mofile = sprintf('%1$s-%2$s.mo', 'leyka-rbk-money', $locale);

    // Setup paths to current locale file
    $mofile_local = $plugin_lang_dir.$mofile;
    $mofile_global = WP_LANG_DIR.'/leyka-rbk-money/'.$mofile;

    if(file_exists($mofile_global)) {
        // Look in global /wp-content/languages/edd folder
        load_textdomain('leyka-rbk-money', $mofile_global);
    } elseif(file_exists(WP_PLUGIN_DIR.'/'.$mofile_local)) {
        // Look in local /wp-content/plugins/easy-digital-donates/languages/ folder
        load_textdomain('leyka-rbk-money', WP_PLUGIN_DIR.'/'.$mofile_local);
    } else {
        // Load the default language files
        load_plugin_textdomain('leyka-rbk-money', false, $plugin_lang_dir);
    }

    // Base Leyka isn't defined, deactivate this plugin:
    if( !defined('LEYKA_VERSION') ) {
        if( !function_exists('deactivate_plugins') )
            require_once(ABSPATH.'wp-admin/includes/plugin.php');
        @deactivate_plugins(__FILE__);
    }
}
add_action('plugins_loaded', 'leyka_rbk_money_plugins_loaded');

function leyka_rbk_money_init(){
    /** Add RBK Money to the gateways list by filter hook. */
    function leyka_rbk_money_gateways($options){
        $options['rbk_money'] = array(
            'admin_label' => __('RBK Money', 'leyka-rbk-money'),
            'checkout_label' => __('RBK Money', 'leyka-rbk-money')
        );
        return $options;
    }
    add_filter('edd_payment_gateways', 'leyka_rbk_money_gateways', 5);

    /** RBK checkout form, so user can fill gateway specific fields. */
//    add_action('edd_rbk_money_cc_form', function(){
//    });

    /** Do some validation on our gateway specific fields if needed. */
//    add_action('edd_checkout_error_checks', function($checkout_form_data){
//    });

    /** Do the gateway's data processing: redirect, saving data in DB, etc. */
    function leyka_rbk_money_processing($payment_data){
        global $edd_options;

        if(empty($edd_options['rbk_money_id'])) {
            edd_set_error('rbk_money_id_is_missing', __('Error: donations receiver\'s RBK Money shop ID has not been set. Please, report it to him.', 'leyka-rbk-money'));
            edd_send_back_to_checkout('?payment-mode='.$payment_data['post_data']['edd-gateway']);
        } elseif( !ctype_digit($edd_options['rbk_money_id']) && !filter_var($edd_options['rbk_money_id'], FILTER_VALIDATE_EMAIL) ) {
            edd_set_error('rbk_id_is_invalid', __('Error: donations receiver\'s RBK Money shop ID is incorrect. Please, report it to him.', 'leyka-rbk-money'));
            edd_send_back_to_checkout('?payment-mode='.$payment_data['post_data']['edd-gateway']);
        } else { // Success, redirect to RBK to donate:
            leyka_insert_payment($payment_data); // Process the payment on our side

            $currency = $edd_options['currency'];
            switch(trim($edd_options['currency'], '.')) {
                case 'руб':
                case 'р':
                case 'RU':
                    $currency = 'RUR';
            }
            header('location: https://rbkmoney.ru/acceptpurchase.aspx?eshopId='.$edd_options['rbk_money_id'].'&recipientCurrency='.$currency.'&recipientAmount='.$payment_data['price']);
            flush();
        }

    }
    add_action('edd_gateway_rbk_money', 'leyka_rbk_money_processing');
}
add_action('init', 'leyka_rbk_money_init', 1);

function leyka_rbk_money_admin_init(){
    // Base Leyka isn't defined, deactivate this plugin:
    if( !defined('LEYKA_VERSION') ) {
        if( !function_exists('deactivate_plugins') )
            require_once(ABSPATH.'wp-admin/includes/plugin.php');
        deactivate_plugins(__FILE__);
        echo __('<div id="message" class="error"><strong>Error:</strong> base donations plugin is missing or inactive. It is required for RBK Money gateway module to work. RBK Money plugin will be deactivated.</div>', 'leyka-rbk-money');
    }
    
    // Add settings link on plugin page:
    function leyka_rbk_plugin_page_links($links){
        array_unshift(
            $links,
            '<a href="'.admin_url('edit.php?post_type=download&page=edd-settings&tab=gateways#rbk_settings').'">'.__('Settings').'</a>'
        );
        return $links;
    }
    add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'leyka_rbk_plugin_page_links');

    function leyka_rbk_money_options($options){
        array_push(
            $options,
            array(
                'id' => 'rbk_money_settings',
                'name' => '<h4 id="rbk_settings">'.__('RBK Money Settings', 'leyka-rbk-money').'</h4>',
                'type' => 'header',
                'desc' => '',
            ),
            array(
                'id' => 'rbk_money_id',
                'name' => __('RBK Money shop ID', 'leyka-rbk-money'),
                'desc' => __('Enter your RBK Money shop ID', 'leyka-rbk-money'),
                'type' => 'text',
                'size' => 'regular'
            ),
            array(
                'id' => 'rbk_money_desc',
                'name' => __('RBK Money gateway description', 'leyka-rbk-money'),
                'desc' => __('Enter RBK Money gateway description that will be shown to the donor when this gateway will be selected for use', 'leyka-rbk-money'),
                'type' => 'rich_editor',
                'std' => '<a href="www.rbkmoney.ru/">Платежный сервис RBK Money</a> представляет собой современную, простую и удобную платформу, для осуществления переводов различными популярными способами, включая банковские карты VISA и Mastercard, мобильные и онлайн платежи, широкую сеть оффлайн отделений и терминалов и множество других способов платежа.
 
Также у пользователей есть возможность создания и использования электронного кошелька RBK Money, с помощью которого можно осуществлять переводы за товары и услуги в интернет-магазинах, коммунальные услуги, услуги мобильной связи, а также осуществлять вывод средств на банковские карты.
 
RBK Money обеспечивает техническую надежность и гарантированную защиту от чардж-бэков и фрода при оплате картами и любым другим способом.'
            )
        );
        return $options;
    }
    add_filter('edd_settings_gateways', 'leyka_rbk_money_options');

    /** Add icons option to the icons list. */
    function leyka_rbk_money_icons($icons){
        $subplugin_url = rtrim(WP_PLUGIN_URL.'/'.basename(dirname(__FILE__)), '/').'/';

        $icons[$subplugin_url.'icons/rbk_s.png'] = __('RBK money small (59x35 px)', 'leyka-rbk-money');
        $icons[$subplugin_url.'icons/rbk_m.png'] = __('RBK money medium (86x51 px) (recommended)', 'leyka-rbk-money');
        $icons[$subplugin_url.'icons/rbk_b.png'] = __('RBK money big (135x80 px)', 'leyka-rbk-money');

        return $icons;
    }
    add_filter('edd_accepted_payment_icons', 'leyka_rbk_money_icons');
}
add_action('admin_init', 'leyka_rbk_money_admin_init', 1);