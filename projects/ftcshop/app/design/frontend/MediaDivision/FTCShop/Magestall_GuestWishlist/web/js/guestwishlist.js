/**
 * Magestall GuestWishlist
 *
 * @category  Magestall
 * @package   Magestall_GuestWishlist
 * @author    Magestall Team <info@magestall.com>
 * @copyright 2019 Magestall (https://www.magestall.com)
 * @license   https://www.magestall.com/license-agreement.html License
 * @link      https://www.magestall.com
 */
var GuestWishList = {
    options: {
        cookie: 'guestwishlist',
        cookielife:30,
        addWishTitle:'',
        removeWishTitle:'',
        compareLink: 'compare-products-link',
        addWishAction: 'add-to-wishlist',
        removeWishAction: 'remove-in-wishlist',
        removeWish: '[data-wish-role=remove]',
        removeAll: '[data-role=removeall]',
        ajaxUrl: '',
    },

    init: function (url, encodedUrl, cookielife, addtitle, removetitle) {
        this.options.ajaxUrl = url;
        this.options.cookielife = cookielife;
        this.options.addWishTitle  = addtitle;
        this.options.removeWishTitle = removetitle;
        this.options.encodedUrl = encodedUrl;
        var guestObj = this;
        jQuery(window).ready(function () {
            guestObj.build();
            guestObj.initControls();
        });
    },
    
    initControls: function () {
        var guestObj = this;
        jQuery('[data-action='+guestObj.options.addWishAction+']').unbind('click').on('click', function (event) {
            event.stopImmediatePropagation();
            var $data = jQuery(this).data('post');
            if(!jQuery(this).hasClass('active')) {
                jQuery(this).addClass('active');
                jQuery(this).attr('data-action',guestObj.options.removeWishAction);
                jQuery(this).attr('title',guestObj.options.removeWishTitle);
                guestObj.addProduct($data.data.product);
                guestObj.build();
            }
            return false;
        });

        jQuery('[data-action='+guestObj.options.removeWishAction+']').unbind('click').on('click', function (event) {
            event.stopImmediatePropagation();
            var $data = jQuery(this).data('post');
            if(jQuery(this).hasClass('active')) {
                jQuery(this).removeClass('active');
                jQuery(this).attr('data-action',guestObj.options.addWishAction);
                jQuery(this).attr('title',guestObj.options.addWishTitle);
                guestObj.removeProduct($data.data.product);
                guestObj.build();
            }
            return false;
        });
        
        jQuery(guestObj.options.removeWish).on('click', function (event) {
            event.stopImmediatePropagation();
            var id = jQuery(this).data('item-id');
            guestObj.removeProduct(id);
            jQuery('#item_' + id).remove();
            guestObj.build();
            return false;
        });
        
        jQuery(guestObj.options.removeAll).on('click', function (event) {
            guestObj.setCookie([]);
            location.reload();
            return false;
        });
    },

    build: function () {
        var guestObj = this;
        var wishlist = this.getCookie();
        jQuery('[data-action='+guestObj.options.addWishAction+']').each(function(i,e){
            if(wishlist.indexOf(jQuery(e).data('post').data.product.toString()) !== -1) {
                if(!jQuery(e).hasClass('active')) {
                    jQuery(e).addClass('active');
                    jQuery(this).attr('data-action',guestObj.options.removeWishAction);
                    jQuery(this).attr('title',guestObj.options.removeWishTitle);
                }
            }
        });
        jQuery('[data-action='+guestObj.options.removeWishAction+']').each(function(i,e){
            if(wishlist.indexOf(jQuery(e).data('post').data.product.toString()) === -1) {
                jQuery(e).removeClass('active');
                jQuery(this).attr('data-action',guestObj.options.addWishAction);
                jQuery(this).attr('title',guestObj.options.addWishTitle);
            }
        });
        var params = {'uenc':guestObj.options.encodedUrl};
        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: guestObj.options.ajaxUrl,
            data: 'uenc='+guestObj.options.encodedUrl,
            showLoader: true,
            success: function(data) {
                if (jQuery('.action.guestwishlist .counter').length > 0) {
                    jQuery('.action.guestwishlist .counter').html(data.count);
                }
                if (jQuery('.sidebar .block-wishlist').length > 0){
                    jQuery(data.list).replaceAll('.sidebar .block-wishlist');
                }
                guestObj.initControls();
            }
        });
    },
    
    addProduct: function (id) {
        var wishlist = this.getCookie();
        if (wishlist.indexOf(id) === -1) {
            if (wishlist[0] == '') {
                wishlist[0] = id;
            } else {
                wishlist.push(id);
            }
            this.setCookie(wishlist);
        }
    },
    
    removeProduct: function(id){
        var currentWishlist = this.getCookie();
        var wishlist = [];
        jQuery.map(currentWishlist, function (val) {
            if (val != id)
                wishlist.push(val);
        });
        this.setCookie(wishlist);
    },
    
    setCookie: function (value) {
        jQuery.cookie(this.options.cookie, value, {expires: this.options.cookielife, path: '/'});
    },
    
    getCookie: function () {
        // Get the cookie value using jQuery.cookie
        var data = jQuery.cookie(this.options.cookie);
    
        // If no cookie is set, return an empty array
        if (data == null) {
            data = [];
        } else {
            // If the cookie is set, split it by commas to return an array
            data = data.split(',');
        }
    
        return data;
    },
};