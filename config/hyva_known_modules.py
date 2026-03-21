"""
Hyvä Compatibility Database — known modules and their Hyvä support status.
Sourced from https://gitlab.hyva.io/hyva-themes/hyva-compat (public tracker).
"""

# Status values: "official", "community", "none", "built-in", "email-only"
# "official" = Hyvä provides compatibility module
# "community" = Community-contributed compatibility
# "none" = No known compatibility module, needs custom work
# "built-in" = Hyvä natively supports this
# "email-only" = Email templates only, no frontend impact

HYVA_COMPAT_MODULES = {
    # Amasty
    "Amasty_Shopby": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-shopby", "notes": "Layered Navigation"},
    "Amasty_ShopbyBrand": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-shopby-brand"},
    "Amasty_ShopbyPage": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-shopby-page"},
    "Amasty_ShopbySeo": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-shopby-seo"},
    "Amasty_Xnotif": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-xnotif", "notes": "Out of Stock Notification"},
    "Amasty_GiftCard": {"status": "none", "notes": "Gift Card Pro — no known Hyvä compat"},
    "Amasty_GiftCardPro": {"status": "none", "notes": "Gift Card Pro — no known Hyvä compat"},
    "Amasty_Feed": {"status": "built-in", "notes": "Backend only, no frontend impact"},
    "Amasty_Smtp": {"status": "built-in", "notes": "Backend only, no frontend impact"},
    "Amasty_Reports": {"status": "built-in", "notes": "Backend only"},
    "Amasty_InvisibleCaptcha": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-invisible-captcha"},
    "Amasty_GeoipRedirect": {"status": "built-in", "notes": "Backend redirect logic, no frontend templates"},
    "Amasty_InstagramFeed": {"status": "none", "notes": "Needs custom Hyvä template"},
    "Amasty_Scroll": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-scroll", "notes": "Infinite/Ajax Scroll"},

    # Algolia
    "Algolia_AlgoliaSearch": {"status": "community", "package": "hyva-themes/hyva-compat-algolia", "notes": "Algolia Search integration"},

    # Mirasvit
    "Mirasvit_Search": {"status": "none", "notes": "Mirasvit Search Suite — needs custom compat"},
    "Mirasvit_SearchAutocomplete": {"status": "none", "notes": "Search Autocomplete — needs custom compat"},
    "Mirasvit_Misspell": {"status": "none", "notes": "Search misspell — needs custom compat"},
    "Mirasvit_SearchElastic": {"status": "none", "notes": "Backend, likely no frontend impact"},
    "Mirasvit_SearchLanding": {"status": "none", "notes": "Search landing pages — may need compat"},
    "Mirasvit_SearchReport": {"status": "built-in", "notes": "Backend reporting only"},
    "Mirasvit_Report": {"status": "built-in", "notes": "Backend reporting only"},
    "Mirasvit_Core": {"status": "built-in", "notes": "Core library, no frontend"},
    "Mirasvit_Rma": {"status": "none", "notes": "RMA — needs custom Hyvä compat module"},

    # Payment
    "Payone_Core": {"status": "community", "package": "hyva-themes/hyva-compat-payone", "notes": "PAYONE Payment"},

    # Mageplaza
    "Mageplaza_Core": {"status": "built-in", "notes": "Core library"},
    "Mageplaza_StoreLocator": {"status": "none", "notes": "Needs custom Hyvä frontend"},
    "Mageplaza_Productslider": {"status": "none", "notes": "Product Slider — needs custom Hyvä template"},
    "Mageplaza_CurrencyFormatter": {"status": "built-in", "notes": "Backend/JS utility"},
    "Mageplaza_EditOrder": {"status": "built-in", "notes": "Admin only"},

    # Scommerce
    "Scommerce_GoogleTagManagerPro": {"status": "community", "package": "hyva-themes/hyva-compat-scommerce-gtm", "notes": "GTM Pro"},
    "Scommerce_TrackingBase": {"status": "community", "notes": "Part of GTM Pro compat"},
    "Scommerce_Core": {"status": "built-in"},
    "Scommerce_CspHelper": {"status": "built-in", "notes": "CSP config, no frontend"},

    # Xtento
    "Xtento_PdfCustomizer": {"status": "built-in", "notes": "PDF generation — backend/email only"},
    "Xtento_XtCore": {"status": "built-in", "notes": "Core library"},

    # Others
    "Ebizmarts_MailChimp": {"status": "none", "notes": "MailChimp integration — needs custom compat for subscribe forms"},
    "Lof_NextGenImages": {"status": "none", "notes": "WebP images — Hyvä handles images differently"},
    "Lof_Webp2": {"status": "none", "notes": "WebP — may not be needed with Hyvä"},
    "Magenizr_ResetUiBookmarks": {"status": "built-in", "notes": "Admin only"},
    "Faonni_IndexerUrlRewrite": {"status": "built-in", "notes": "Backend indexer"},
    "Ulmod_CartEdit": {"status": "none", "notes": "Cart Edit — needs custom Hyvä compat"},
    "Magestall_GuestWishlist": {"status": "none", "notes": "Guest Wishlist — needs custom Hyvä compat"},
    "Shopigo_SwissPost": {"status": "built-in", "notes": "Shipping backend"},

    # DHL
    "Dhl_Shipping": {"status": "built-in", "notes": "Shipping carrier — backend/checkout integration"},
    "Dhlexpress_Services": {"status": "built-in", "notes": "DHL Express services — backend"},

    # HubSpot
    "Makewebbetter_HubIntegration": {"status": "built-in", "notes": "HubSpot backend integration"},
    "Makewebbetter_HubGuestUser": {"status": "built-in", "notes": "HubSpot backend"},

    # MediaDivision custom modules
    "MediaDivision_Basics": {"status": "none", "notes": "Custom module — needs analysis"},
    "MediaDivision_CheckoutSteps": {"status": "none", "notes": "Custom checkout — needs Hyvä rewrite"},
    "MediaDivision_GalleryImages": {"status": "none", "notes": "Custom gallery — needs Hyvä rewrite"},
    "MediaDivision_Image": {"status": "none", "notes": "Custom image handling — needs analysis"},
    "MediaDivision_Magmi": {"status": "built-in", "notes": "Data import — backend"},
    "MediaDivision_ShippingMethods": {"status": "none", "notes": "Custom shipping — may need checkout compat"},
    "MediaDivision_Store": {"status": "none", "notes": "Custom store logic — needs analysis"},
    "MediaDivision_StoreLocator": {"status": "none", "notes": "Store locator — needs Hyvä frontend"},
    "MediaDivision_SwatchImages": {"status": "none", "notes": "Custom swatch images — needs Hyvä rewrite"},
    "MediaDivision_TexData": {"status": "none", "notes": "Custom module — needs analysis"},
}

# Modules that are part of Magento core and have Hyvä equivalents
MAGENTO_CORE_HYVA_MAPPING = {
    "Magento_Catalog": "hyva-themes/magento2-default-theme",
    "Magento_Checkout": "hyva-themes/magento2-default-theme",
    "Magento_Customer": "hyva-themes/magento2-default-theme",
    "Magento_Cms": "hyva-themes/magento2-default-theme",
    "Magento_Search": "hyva-themes/magento2-default-theme",
    "Magento_LayeredNavigation": "hyva-themes/magento2-default-theme",
    "Magento_ConfigurableProduct": "hyva-themes/magento2-default-theme",
    "Magento_CatalogWidget": "hyva-themes/magento2-default-theme",
    "Magento_Store": "hyva-themes/magento2-default-theme",
    "Magento_Sales": "hyva-themes/magento2-default-theme",
    "Magento_Tax": "hyva-themes/magento2-default-theme",
    "Magento_Weee": "hyva-themes/magento2-default-theme",
    "Magento_ProductAlert": "hyva-themes/magento2-default-theme",
    "Magento_PageBuilder": "hyva-themes/magento2-default-theme",
    "Magento_OfflinePayments": "hyva-themes/magento2-default-theme",
    "Magento_CheckoutAgreements": "hyva-themes/magento2-default-theme",
}

# Composer package → Hyvä compat mapping (for precise matching)
COMPOSER_PACKAGE_COMPAT = {
    "algolia/algoliasearch-magento-2": {"status": "community", "package": "hyva-themes/hyva-compat-algolia", "notes": "Algolia Search"},
    "amasty/feed": {"status": "built-in", "notes": "Product Feed — backend only"},
    "amasty/geoipredirect": {"status": "built-in", "notes": "GeoIP Redirect — backend logic"},
    "amasty/gift-card-pro": {"status": "none", "notes": "Gift Card Pro — needs custom Hyvä compat"},
    "amasty/instagram-feed": {"status": "none", "notes": "Instagram Feed widget — needs custom Hyvä template"},
    "amasty/module-ajax-scroll-subscription-package": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-scroll", "notes": "Ajax/Infinite Scroll"},
    "amasty/module-reports-lite": {"status": "built-in", "notes": "Reports — admin only"},
    "amasty/module-shopby-pro": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-shopby", "notes": "Improved Layered Navigation Pro"},
    "amasty/shopby": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-shopby", "notes": "Improved Layered Navigation"},
    "amasty/smtp": {"status": "built-in", "notes": "SMTP — backend only"},
    "amasty/xnotif": {"status": "community", "package": "hyva-themes/hyva-compat-amasty-xnotif", "notes": "Out of Stock Notification"},
    "dhl/shipping-m2": {"status": "built-in", "notes": "DHL Shipping — checkout backend"},
    "ebizmarts/mailchimp-lib": {"status": "none", "notes": "MailChimp library — needs custom subscribe form compat"},
    "experius/module-emailcatcher": {"status": "built-in", "notes": "Email Catcher — admin only"},
    "mirasvit/module-rma": {"status": "none", "notes": "RMA — needs custom Hyvä frontend"},
    "mpdf/mpdf": {"status": "built-in", "notes": "PDF library — no frontend"},
    "mpdf/qrcode": {"status": "built-in", "notes": "QR code library — no frontend"},
    "rosell-dk/webp-convert": {"status": "built-in", "notes": "WebP conversion — backend"},
    "yireo/magento2-emailtester2": {"status": "built-in", "notes": "Email tester — admin only"},
    "laminas/laminas-mail": {"status": "built-in", "notes": "Mail library — no frontend"},
}
