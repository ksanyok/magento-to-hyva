require(["jquery"], function ($) {
  $.migrateMute = true;
  $.migrateTrace = false;

  $(document).ready(function () {
    let html = $("html");
    let body = $("body");
    let page = checkPage();
    let lang = html.attr("lang");
    let baseUrl = document.location.toString();
    let shopToken = body[0].className.split(" ")[0];
    let shop = shopToken === "default" ? "ch" : shopToken.substr(3, 2);
    let isMobile = $(window).width() <= 768;

    let imageSlider = body.find(".image-slider");
    let languageOverlay = $(".language-overlay");
    let currencyOverlay = $(".currency-overlay");
    let minicartOverlay = $(".minicart-overlay");
    let quickViewWishlist = $(".quick-view .add-to-wishlist");
    let checkoutPage = $(".checkout-index-index");
    let applyFiltersButton = $(".am_shopby_apply_filters");
    let quickViews = $(".quick-view");

    let mode = setMode();
    let chosenLang = setChosenLang();
    let chosenCountry = setChosenCountry();

    let cartSummaryInterval;
    let checkoutInterval;
    let paymentStepInterval;
    let imageSliderInterval;

    let fixedHeader = false;
    let stickyFilter = false;
    let imageSlide = 1;
    let imageSlideTime = 5000;
    let imageSliderAutostart = true;

    let preparedCheckoutSteps = {
      login: false,
      shipping: false,
      payment: false,
      confirm: false,
      done: false,
    };



    /** SEITENEIGENSCHAFTEN LOGGEN
     * Loggt wesentliche Eigenschaften der geladenen Seite in der Entwicklerkonsole
     * Wird wie alle Aufrufe von log() nur lokal und in der Staging-Umgebung ausgeführt
     */

    log("lang is " + lang);
    log("page is " + page);
    log("shop is " + shop);



    /** GRUNDFUNKTIONEN AUSFÜHREN
     * Ruft unabhängig von der geladenen Seite einige nützliche Funktionen auf
     * Hier handelt es sich hauptsächlich um layoutspezifische Anpassungen
     */

    positionOverlays();
    initRegionChooser();
    replaceCurrencyName();
    moveNewsletterSubscription();
    addStoresLink();



    /** COOKIES AKTIVIEREN
     * Wählt beim ersten Aufruf der Seite alle Checkboxen im Cookie-Auswahlfenster an
     * Der Timeout ist nötig, weil das Auswahlfenster oft erst zeitverzögert erscheint
     */

    setTimeout(function () {
      $(".amgdprcookie-input:not(:checked)").trigger("click");
    }, 2000);



    /** SEITE ÜBERPRÜFEN
     * Überprüft, welche Seitenart im Shop aufgerufen wurde
     * Führt bei Bedarf die seitenspezifischen Funktionen aus
     */

    switch (page) {
      case "home":
        initImageSlider();
        initProductSlider();
        body.find(".home-arrivals > div").addClass("animated");
        break;
      case "category":

        function CheckForCategoryTitle() {
          if ($(".category-image .page-title-wrapper").length > 0) {

          } else {
            if ($('#page-title-heading').length > 0) {
              $clonetitle = $('#page-title-heading').clone();
              $clonetitle.appendTo('.category-view .category-image');
              clearInterval(CheckForCategoryTitleInterval);
            }
          }
        }
        var CheckForCategoryTitleInterval = window.setInterval(CheckForCategoryTitle, 200);

        initQuickViewSwatches();
        initFilterLists();

        break;

      case "product":
        initProductSlider();
        initProductVideos();
        //  selectFirstSwatchOptions(1000);
        //  selectFirstSwatchOptions(2000);
        //  selectFirstSwatchOptions(3000);
        deSelectFirstSwatchOptions(1000);
        deSelectFirstSwatchOptions(2000);
        deSelectFirstSwatchOptions(3000);
        break;
      case "cart":
        initProductSlider();
        cartSummaryInterval = setInterval(changeSummaryOrder, 100);
        break;
      case "checkout":
        checkoutInterval = setInterval(prepareCheckout, 100);
        break;
      default:
        break;
    }



    $(document).on("click", ".amshopby-items .amshopby-item .amshopby-remove", function () {
      $(".am-show-button .am-button").click()
    });


    /** FUNKTIONSAUFRUFE HINZUFÜGEN
     * Fügt zu zahlreichen Elementen auf der Seite Funktionsaufrufe hinzu, welche die Funktionalität der Seite wesentlich erweitern
     * In der Regel gibt der Selektor Aufschluss darüber, um welches Element es sich handelt, weswegen sie nicht einzeln dokumentiert sind
     * Meistens werden eine oder mehrere Funktionen aufgerufen, deren Funktionsweise und Zweck weiter unten ausführlicher erläutert sind
     */

    body.on("click", ".product-info-main .swatch-option.color, .product-info-main .swatch-option.image", function () {
      for (let i = 10; i < 1000; i += 100) {
        initProductReminder(i);
      }
    });

    body.on("click", ".page-main", function () {
      if (!isMobile || !html.hasClass("nav-open")) {
        return;
      }

      html.removeClass("nav-before-open nav-open");
      body.find(".navigation").removeClass("toggled");
      body.find("#store\\.settings").removeClass("visible");
    })




    body.on("click", ".popup-link", function () {
      let popup = $(this).attr("class").split(" ").pop();

      body.find(".popups .popup").addClass("hidden");
      body.find(".popups, .popups ." + popup).removeClass("opaque hidden");
      body.find(".popups .close-icon, .popups .close-button").clone().appendTo(".popups ." + popup);

      setTimeout(function () {
        body.find(".popups").addClass("opaque");
      }, 10);
    });

    body.on("click", ".popups .popup .close-icon, .popups .popup .close-button", function () {
      body.find(".popups").removeClass("opaque");

      setTimeout(function () {
        body.find(".popups").addClass("hidden");
      }, 450);
    });







    body.on("click", ".quick-view .close", function () {
      let quickView = body.find(".quick-view.visible");
      $(".detached-quick-view").find(".hover-image").removeClass("fixed");
      changeVisibility(quickView, quickView.hasClass("visible opaque"), 450);

      setTimeout(function () {
        quickView.detach().appendTo(".detached-quick-view .product-item > div, .detached-quick-view.product-item > div");
        $(".detached-quick-view").removeClass("detached-quick-view");
      }, 450);
    });

    $(".product-item-info").on("click", function (element) {
      let classes = element.target.className;

      if (!classes.includes("view-product") && !classes.includes("swatch-option")) {
        document.location.href = $(this).find("a").attr("href");
      }
    });

    $(".product-options-wrapper div").click(function () {
      changeProductDescription();
    });

    body.on("click", ".image-slider .arrow, .image-slider .dot", function () {
      clearInterval(imageSliderInterval);
      changeImageSlide($(this));
    });


    body.on("change", ".amshopby-slider-container input", function () {
      let values = $(this).val().split("-");
      let currency = $("#switcher-currency-trigger-nav").find(".visible").text();

      $(".am-slider .left.label").text(values[0] + " " + currency);
      $(".am-slider .right.label").text(values[1] + " " + currency);
    });

    $(document).on("click", ".rma-one-item .item-description", function (e) {
      e.preventDefault();  // Avoid default click behavior
      const checkbox = $(this).find("input[type='checkbox']");
      if (!checkbox.is(e.target)) {  // Avoid recursion
        checkbox.prop("checked", !checkbox.prop("checked")); // Toggle state
        $(".rma-rma-new .ui-rma-items .rma-one-item .item-options").toggle();
      }
    });

    body.on("click", ".mst-rma-create__order-selector button", function () {
      body.addClass("rma-open");
    });

    body.on("click", ".toggle-category-filter", function () {
      toggleCategoryFilter();
    });

    body.on("click", ".product-popup-link", function () {
      changeVisibility($(".popup-container." + $(this).data("popup")), false, 450);
    });

    body.on("click", ".popup-container .popup .close-icon, .popup-container .popup .close-button", function () {
      changeVisibility($(".popup-container"), true, 450);
    });



    body.on("click", ".view-product", function (event) {
      event.preventDefault();
      let parent = $(this).parent().parent().parent().parent().parent().parent();

      if (page === "category") {
        parent = $(this).parent().parent().parent().parent().parent();
      }

      let quickView = parent.find(".quick-view");
      parent.addClass("detached-quick-view");
      quickView.detach().appendTo("body");

      parent.find(".hover-image").addClass("fixed");
      quickView.find(".swatch-option:first-of-type").trigger("click");
      changeVisibility(quickView, quickView.hasClass("visible opaque"), 450);
    });

    /*
          body.on("click", "#minicart-content-wrapper a", function() {
              document.location = $(this).find("a").attr("href");
          });
    */

    body.on("click", ".quick-view .gallery-image", function () {
      $(".quick-view.visible .product-image-photo").attr("src", $(this).attr("src"));
    });

    body.on("click", ".quick-view .swatch-option", function () {
      let detachedQuickView = $(".detached-quick-view");
      detachedQuickView.find(".product-item-details #" + $(this).attr("id")).click();
    });

    body.on("click", ".change-qty.minus", function () {
      let input = $(this).parent().find("input.qty");

      if (input.get(0).value > 1) {
        input.get(0).value--;
      }

      if (page === "cart") {
        $("button.action.update").trigger("click");
      }
    });

    body.on("click", ".change-qty.plus", function () {
      let input = $(this).parent().find("input.qty");
      input.get(0).value++;

      if (page === "cart") {
        $("button.action.update").trigger("click");
      }
    });

    body.on("click", ".cms-index-index .zone", function () {
      window.location = $(this).find("a").attr("href");
    });

    body.on("click", ".region-chooser .country", function () {
      let element = $(this);

      body.find(".region-chooser .country").removeClass("active");
      element.addClass("active");
      chosenCountry = element.data("country");

      updateRegionLangAndCurrency();
    });

    body.on("change", ".region-chooser .languages select", function () {
      chosenLang = $(this).val();
    });

    body.on("click", ".region-chooser .apply", function () {
      var url = $('input[name="store_url_' + chosenLang + '-' + chosenCountry + '"]').val();
      window.location = url;
    });

    /*
          $(document).on("click", ".catalog-product-view .swatch-option.color, .catalog-product-view .swatch-option.color_name", function() {
              setTimeout(function() {
                  addConfigurableProductImage();
              }, 500);
          });
    */

    $(document).on("click", "#login_register .action.continue, #payment .action.continue, .opc-progress-bar-item", function () {
      checkoutInterval = setInterval(prepareCheckout, 100);
    });

    checkoutPage.on("click", ".billing-address-same-as-shipping-block", function () {
      checkoutPage.find("#checkout-payment-method-load").toggleClass("right");
      checkoutPage.find(".checkout-billing-address > fieldset").toggleClass("visible");

      if ($(".field-select-billing").is(":visible")) {
        checkoutPage.find(".actions-toolbar .action-update").addClass("margin-top");
      }
    });




    applyFiltersButton.on("click", function (element) {
      if (!element.target.className.includes("am-button")) {
        applyFiltersButton.find(".am-button").trigger("click");
        location.reload();
      }
    });

    $(".popup-container").on("click", function (element) {
      if (element.target.className.includes("popup-container")) {
        changeVisibility($(".popup-container"), true, 450);
      }
    });

    quickViews.on("click", function (element) {
      if (element.target.className.includes("quick-view visible")) {
        let quickView = $(this);
        $(".detached-quick-view").find(".hover-image").removeClass("fixed");
        changeVisibility(quickView, quickView.hasClass("visible opaque"), 450);

        setTimeout(function () {
          quickView.detach().appendTo(".detached-quick-view .product-item > div, .detached-quick-view.product-item > div");
          $(".detached-quick-view").removeClass("detached-quick-view");
        }, 450);
      }
    });

    $(".region-chooser").on("click", function (element) {
      if (element.target.className.includes("region-chooser visible")) {
        let regionChooser = body.find(".region-chooser");
        let storeSettings = body.find("#store\\.settings");

        storeSettings.toggleClass("hidden");
        changeVisibility(regionChooser, regionChooser.hasClass("visible opaque"), 450);
      }
    });







    body.on("click", "footer .section h3", function () {
      $(this).parent().toggleClass("open");
    });

    body.on("click", ".product-slider .dot:not(.active)", function () {
      let dot = $(this);
      let position = dot.data("position");
      let productItems = body.find(".product-slider .product-items");

      dot.parent().find(".dot").removeClass("active");
      dot.addClass("active");
      productItems.animate({ scrollLeft: productItems.width() * position }, 450);
    });

    body.on("click", ".back-to-top", function () {
      $("html, body").animate({ scrollTop: 0 }, "slow");
    });

    body.on("click", "#shipping-method-buttons-container button.continue", function () {
      paymentStepInterval = setInterval(checkPaymentStep, 100);
    });

    $(document).on("scroll", doAnimations);

    $(document).trigger("scroll");

    $(window).on("resize", function () {
      positionOverlays();
      isMobile = $(window).width() <= 768;
    });


















    $(document).on("click", "header .dismiss", function () {
      $(document).find(".ftc-header header.msg").addClass("dismissed");
      $(document).find(".page-wrapper").addClass("dismissed-header");
    });


    $(document).on("click", ".ftc-header #searchicon", function () {
      $('.block-search').fadeIn(450);
      $('.form.minisearch').find("#search").focus();
      if (!$('.clone-search-popular').length > 0) {
        var Clone_Title = $('.mst-searchautocomplete__index.popular .mst-searchautocomplete__index-title span:first-child').html();
        $('.form.minisearch').after('<div class="clone-search-popular"><span>' + Clone_Title + '</span><ul></ul></div>');
        $('.mst-searchautocomplete__index.popular ul li').each(function () {
          var thisval = $(this).find("a").html();
          var thislink = "/" + shop + "-" + lang + "/catalogsearch/result/?q=" + thisval;
          $('.clone-search-popular ul').append('<li><a href="' + thislink + '">' + thisval + '</a></li>');
        });
      }
    });

    function searchFullscreen() {
      if ($(".mst-searchautocomplete__index.magento_catalog_product ul li").length > 0) {
        if (!$('.block.block-search').hasClass("fullscreen")) {
          $('.block.block-search').addClass("fullscreen");
          $('body').addClass("noscroll");
        }
      }
    }
    var intervalId = window.setInterval(searchFullscreen, 50);

    $(document).on("click", ".block-search .close", function () {
      $('.block-search').fadeOut(450);
      $('.block.block-search #search').val("");
      $('.mst-searchautocomplete__wrapper').remove();
      $('.block.block-search').removeClass("fullscreen");
      $('body').removeClass("noscroll");
    });







    $(document).on("click", ".minicart-overlay, #minicart-close-button", function () {
      $(".block.block-minicart").fadeOut(450);
    });

    $(document).on("click", ".minicart-wrapper .showcart", function (e) {
      e.preventDefault();
      $(".block.block-minicart").fadeIn(450);
    });






    $(document).on("click", ".ftc-store-switcher", function () {
      $('.region-chooser').fadeIn(450);
    });

    $(document).on("click", ".region-chooser .close", function () {
      $('.region-chooser').fadeOut(450);
    });










    body.on("click", ".lookbook button", function (event) {
      var thisfilter = $(this).attr("data-filter");
      $('.lookbook button').removeClass("active");
      $(this).addClass("active");
      if (thisfilter == "*") {
        $('.lookbook >section:last-of-type >div').show(200);
      } else {
        $('.lookbook >section:last-of-type >div').hide();
        $('.lookbook img[data-cat="' + thisfilter + '"]').parent().show(200);
      }
    });
    body.on("click", ".lookbook >section:last-of-type >div", function (event) {
      if ($(this).hasClass("active")) {
        $(this).removeClass("active");
        $('.lookbook >section:last-of-type >div').removeClass("inactive");
      } else {
        $('.lookbook >section:last-of-type >div').removeClass("active");
        $('.lookbook >section:last-of-type >div').addClass("inactive");
        $(this).removeClass("inactive").addClass("active");
      }
    });




















    $(document).on("click", "header.msg .dismiss", function () {
      $('.ftc-navigation').removeClass("headermsgisactive");
    });









    if ($('body').hasClass("cms-index-index")) {
      $('.ftc-header').removeClass("fixed");
    }
    const mediaQuery = window.matchMedia('(min-width: 901px)')
    if (mediaQuery.matches) {

      $(document).scroll(function () {
        if ($('body').hasClass("cms-index-index")) {
          if ($(window).scrollTop() === 0) {
            body.find(".ftc-header").removeClass("fixed");
          } else {
            body.find(".ftc-header").addClass("fixed");
          }
        }
      });

    } else {

      body.find(".ftc-header").addClass("fixed");


    }






    $(document).on("click", ".responsive-nav-icon", function () {
      if ($('.ftc-navigation > section > section .left > ul').hasClass("respactive")) {
        $('.ftc-navigation > section > section .left > ul').removeClass("respactive").hide(200);
      } else {
        $('.ftc-navigation > section > section .left > ul').addClass("respactive").show(200);
      }
    });





    /* ******* */
    /* Farbkacheln Katalogseite, wenn mehrfarbiges produkt (color attribute 1-100) */
    /* dann wird ein bildausschnitt gewählt.. als farbkachel.. ansonsten wären sie alle einfach nur schwarz */
    /* ******* */

    function ColorSwatchImage() {

      var ArrColor;
      var ArrImg;
      var ArrId;
      var i;
      var checker = 0;

      if ($('body').hasClass("catalog-category-view")) {
        $('.product-items .product-item').each(function () {
          if (!$(this).hasClass("colorimagechecked")) {
            if ($(this).find(".swatch-attribute.color_name").length > 0) {

              var $productitem = $(this);

              /* ******* */
              /* ******* */
              /* ******* */

              var a = 1;
              $($productitem).find('.product-item-details .swatch-attribute.color_name .swatch-attribute-options > .swatch-option').each(function () {
                if ($(this).attr("option-tooltip-value") == "#000000") {
                  var imgsrc = $($productitem).find(".product-item-photo .SwatchMultiColorImage:nth-child(" + a + ")").attr("data-src");
                  $(this).css("background-image", "url(" + imgsrc + ")");
                  $(this).css("background-position", "center center");
                  $(this).css("background-size", "300%");
                  $(this).css("background-repeat", "no-repeat");
                  $(this).css("background-color", "#ffffff");
                }
                a++;
              });



              $($productitem).addClass("colorimagechecked");
            }
          }
        });
      }
    }

    // ColorSwatchImageInterval = setInterval(ColorSwatchImage, 100);



    /* ******* */
    /* Farbkacheln Produktdetailseite, wenn mehrfarbiges produkt (color attribute 1-100) */
    /* dann wird ein bildausschnitt gewählt.. als farbkachel.. ansonsten wären sie alle einfach nur schwarz */
    /* ******* */


    if ($('body').hasClass("page-product-configurable")) {
      function ColorSwatchImagePDP() {
        $('.swatch-attribute.color_name .swatch-option').each(function () {
          if ($(this).attr("option-tooltip-value") == "#000000" && !$(this).hasClass("checkpassed")) {
            var id = $(this).attr("option-id");
            var imgsrc = $('#ProductImages div[data-farbe="' + id + '"]').attr("data-url");

            $(this).css("background-image", "url(" + imgsrc + ")");
            $(this).css("background-position", "center center");
            $(this).css("background-size", "300%").addClass("swatchbgsizeimportant");
            $(this).css("background-repeat", "no-repeat");
            $(this).css("background-color", "#ffffff");
            $(this).addClass("checkpassed");

          }
        });
      }
      //  ColorSwatchImageInterval = setInterval(ColorSwatchImagePDP, 100);
    }


    /** SEITE ÜBERPRÜFEN
     * Prüft den body der aktuellen Seite und ermittelt, welche Seite geladen wurde
     * Das Ergebnis wird in der Variable page gespeichert, die überall abrufbar ist
     */

    function checkPage() {
      let classes = body.attr("class");

      if (classes.includes("cms-index-index")) {
        return "home";
      } else if (classes.includes("catalog-category-view")) {
        return "category";
      } if (classes.includes("catalog-product-view")) {
        return "product";
      } else if (classes.includes("checkout-cart-index")) {
        return "cart";
      } else if (classes.includes("checkout-index-index")) {
        return "checkout";
      } else {
        return null;
      }
    }



    /** SICHTBARKEIT ÄNDERN
     * Blendet ein Element mit weichem Übergang innerhalb einer Zeitspanne ein oder aus
     * Empfängt das Element, die Sichtbarkeit und die gewünschte Zeitspanne als Argumente
     */

    function changeVisibility(element, visible, time) {
      if (!visible) {
        element.addClass("visible");

        setTimeout(function () {
          element.addClass("opaque");
        }, 10);
      } else {
        element.removeClass("opaque");

        setTimeout(function () {
          element.removeClass("visible");
        }, time + 10);
      }
    }



    /** BILDER-SLIDER INITIALISIEREN
     * Initialisiert den Bilder-Slider auf der Startseite, indem Steuerelemente hinzugefügt werden
     * Die Variable imageSliderAutostart bestimmt, ob der Slider von selbst startet oder nicht
     * Mit imageSlideTime kann festgelegt werden, wie lange jedes einzelne Bild angezeigt wird
     */

    function initImageSlider() {
      let dots = "<div class='dots'>";
      let loaders = "<div class='loaders'>";
      let slides = imageSlider.find(".slide");

      for (let i = 1; i <= slides.length; i++) {
        dots += "<div class='dot-container'><div class='dot dot" + i + "' data-slide='" + i + "'></div></div>";
        loaders += '<div class="loader loader' + i + '"><div class="loader-background"><div class="text"></div></div><div class="spinner-holder-one animate animate-0-25-a"><div class="spinner-holder-two animate animate-0-25-b"><div class="loader-spinner" style=""></div></div></div><div class="spinner-holder-one animate animate-25-50-a"><div class="spinner-holder-two animate animate-25-50-b"><div class="loader-spinner"></div></div></div><div class="spinner-holder-one animate animate-50-75-a"><div class="spinner-holder-two animate animate-50-75-b"><div class="loader-spinner"></div></div></div><div class="spinner-holder-one animate animate-75-100-a"><div class="spinner-holder-two animate animate-75-100-b"><div class="loader-spinner"></div></div></div></div>';
      }

      imageSlider.append(dots + "</div>");
      imageSlider.append(loaders + "</div>");
      imageSlider.append("<div class='prev arrow'></div><div class='next arrow'></div>");

      if (mode !== "local" && imageSliderAutostart) {
        setTimeout(function () {
          imageSlider.find(".dot1").addClass("transparent");
          imageSlider.find(".loader1 .spinner-holder-two").css("transform", "rotate(0deg)");
          imageSliderInterval = setInterval(changeImageSlide, imageSlideTime);
        }, 100);
      }
    }


    /** PRODUKTE-SLIDER INITIALISIEREN
     * Initialisiert den Product-Slider auf der Startseite, der Produktdetailseite und im Warenkorb
     * Fügt ihm Punkte hinzu und erweitert die QuickView-Elemente mit initQuickViewSwatches()
     */

    function initProductSlider() {
      let productSlider = body.find(".block-products-list.product-slider");
      let products = productSlider.find(".product-item");
      let dots = productSlider.find(".dots");


    }



    /** PRODUKTVIDEOS ANPASSEN
     * Passt das Format von Produktvideos auf der Produktdetailseite an
     */

    function initProductVideos() {
      let productVideos = body.find("iframe.video");

      productVideos.css("height", productVideos.width() / 16 * 9 + "px");
    }



    /** PRODUKTVIDEOS ANPASSEN
     * Prüft, ob es auf der Produktdetailseite nicht verfügbare Varianten von Produkten gibt
     * Falls ja, kann man sich für Benachrichtigungen eintragen, wenn das Produkt wieder verfügbar ist
     * Das zu diesem Zweck angezeigte Layout und der zugehörige Button werden mit dieser Funktion angepasst
     * Da das Layout oft zeitverzögert lädt, wird die Funktion mehrmals aufgerufen und prüft jedes Mal die Seite
     */

    function initProductReminder(timeout) {
      log("checking product reminder");

      setTimeout(function () {
        if (body.find(".amstockstatus-stockalert").length === 0) {
          log("no product reminder found");
          body.find(".product-info-main").removeClass("notify");
          return;
        }

        log("product reminder found");
        body.find(".product-info-main").addClass("notify");

        switch (lang) {
          case "de":
            body.find(".amxnotif-block button span").text("Benachrichtigen");
            body.find(".amxnotif-block input").attr("placeholder", "Ihre E-Mail-Adresse");
            body.find(".amstockstatus-stockalert a.action.alert").text("Benachrichtigen");
            break;
          case "en":
            body.find(".amxnotif-block button").text("Notify me");
            body.find(".amstockstatus-stockalert a.action.alert").text("Notify me");
            break;
        }
      }, timeout);
    }

    function deSelectFirstSwatchOptions(timeout) {
      setTimeout(function () {
        if ($('.swatch-option.text.selected').length > 0) {
          $('.swatch-option.text.selected').click();
        }
      }, timeout);
    }


    /** PRODUKTOPTIONEN VORAUSWÄHLEN
     * Wählt auf der Produktdetailseite die erste verfügbare Grösse und Farbe aus
     * Da die Auswahl für Grösse und Farbe oft zeitverzögert lädt, wird die Funktion mehrfach aufgerufen


    function selectFirstSwatchOptions(timeout) {
        setTimeout(function() {
            let swatchInput = body.find(".product-options-wrapper .swatch-input");

            if (swatchInput[1] !== undefined && swatchInput[1].value === "") {
                log("selected swatch options");
                body.find(".swatch-opt .swatch-attribute:nth-of-type(2) .swatch-option:first-of-type").trigger("click");
                body.find(".swatch-opt .swatch-attribute:nth-of-type(1) .swatch-option:first-of-type").trigger("click");
            }
        }, timeout);
    }

   */

    /** SUMMEN IM WARENKORB ANORDNEN
     * Überprüft den Warenkorb und ändert die Anordnung der Summen, sobald diese geladen wurden
     * Da die Summen in der Regel zeitverzögert angezeigt werden, läuft die Funktion in einem Intervall
     * Sobald die Summen gefunden und angepasst wurden, wird das Intervall beendet
     */

    function changeSummaryOrder() {
      log("checking cart summary");

      if (body.find(".totals.sub").length > 0) {
        setTimeout(function () {
          log("changed cart summary");
          clearInterval(cartSummaryInterval);

          body.find(".totals-tax-summary").addClass("hidden");
          body.find(".grand.totals.excl").addClass("hidden");
          // body.find(".totals-tax-details").addClass("shown");
          // body.find(".grand.totals.excl").detach().insertAfter(".totals.sub");
        }, 10);
      }
    }



    /** PRODUKTOPTIONEN IN QUICK VIEWS ANPASSEN
     * Fügt sprachlich passende Titel zu den Quick Views von Produkten hinzu
     * Wird für alle Produkte auf Katalogseiten und in Produkt-Slidern ausgeführt
     */

    function initQuickViewSwatches() {
      switch (lang) {
        case "de":
          quickViews.find(".swatch-attribute.color_name > div").prepend("<h3>Farbe</h3>");
          quickViews.find(".swatch-attribute.size > div").prepend("<h3>Grösse</h3>");
          break;
        case "en":
          quickViews.find(".swatch-attribute.color_name > div").prepend("<h3>Colour</h3>");
          quickViews.find(".swatch-attribute.size > div").prepend("<h3>Size</h3>");
          break;
      }
    }



    /** FILTERLISTE INITIALISIEREN
     * Initialisiert die Filterliste auf Katalogseiten
     * Sorgt für die richtige Anordnung und Beschriftung
     */

    function initFilterLists() {
      log("init filter list (main.js)");

      $("#am-shopby-container").detach().prependTo(".toolbar-products .top-toolbar");
      $(".block-actions.filter-actions").detach().prependTo("#am-shopby-container");
      applyFiltersButton.detach().appendTo(".block.filter");
      $(".am_shopby_apply_filters .am-items").detach().appendTo(".am-show-button");
      $("<label></label>").insertAfter(".filter-list-container:nth-child(1) .item input, .filter-list-container:nth-child(4) .item input");

      let slider = $(".am-slider");
      let sliderLabelContainer = $(".amshopby-slider-display");
      let colorLinks = $(".filter-list-container:nth-child(3) .am-swatch-link");
      let sliderLabels = sliderLabelContainer.text().split(" - ");
      let currency = $("#switcher-currency-trigger-nav").find(".visible").text();

      colorLinks.each(function () {
        let element = $(this);
        element.append("<div class='label'>" + element.data("label") + "</div>");
      });

      sliderLabelContainer.remove();
      slider.append("<div class='left label'>" + sliderLabels[0] + " " + currency + "</div>");
      slider.append("<div class='right label'>" + sliderLabels[1] + " " + currency + "</div>");
    }

    /** Produktbilder Produktdetailseite
     * es werden alle bilder geladen und auf display:none gestellt.
     * je nach geklickter farbe / grösse werden die zugehörigen bilder auf sichtbar gestellt
     * per default / seitenaufruf wird die erste grösse und die erste farbe vorausgewählt. demnach auch die entsprechenden bilder
     */

    // erste farb-swatch vorauswählen

    /*
          function SelectFirstSwatches() {
            if($('body').hasClass("catalog-product-view")){ 
              if($('.swatch-attribute.color_name .swatch-attribute-options > .swatch-option:first-of-type').length>0){ 
                  
                $('.swatch-attribute.color_name .swatch-attribute-options > .swatch-option').each(function(){
                  if(!$(this).hasClass("out-of-stock")){
                    $(this).click();
                    return false;
                  }
                });         
    
                if($('.swatch-attribute.size .swatch-attribute-options > .swatch-option:first-of-type').length>0){ 
    
                  $('.swatch-attribute.size .swatch-attribute-options > .swatch-option').each(function(){
                      if(!$(this).hasClass("out-of-stock")){
                        $(this).click();
                        return false;
                      }
                    });      
    
          
    
                    var colorid = $('.swatch-attribute.color_name .swatch-attribute-options > .swatch-option.selected').attr("option-id");        
                    var sizeid = $('.swatch-attribute.size .swatch-attribute-options > .swatch-option.selected').attr("option-id");
                    $('.product-images .images').html("");  
                    $('.product-images .thumbnails').html("");  
                    $('#ProductImages div').each(function(){
                      var thisFarbe = $(this).attr("data-farbe");
                      var thisGroesse = $(this).attr("data-groesse");
                      var src = $(this).find("img").attr("src");
                      if(colorid == thisFarbe && sizeid == thisGroesse){
                        $('.product-images .images').append('<div class="image-container"><img src="'+src+'" /></div>');
                        $('.product-images .thumbnails').append('<div class="thumbnail-container"><img src="'+src+'" /></div>');
                      }
                    });
                    $('.product-images .images > .image-container:first-child').addClass("active");
                    $('.product-images .thumbnails > .thumbnail-container:first-child').addClass("active");
                    clearInterval(SelectFirstSwatchesInterval);     
                }                    
              }
            }
          }
          var SelectFirstSwatchesInterval = window.setInterval(SelectFirstSwatches, 50);
    
    
    */
    $(document).on("click", ".swatch-attribute.size .swatch-attribute-options > .swatch-option", function () {
      var sizeid = $(this).attr("option-id");
      var colorid = $('.swatch-attribute.color_name .swatch-attribute-options > .swatch-option.selected').attr("option-id");
      $('.product-images .images').html("");
      $('.product-images .thumbnails').html("");
      $('#ProductImages div').each(function () {
        var thisFarbe = $(this).attr("data-farbe");
        var thisGroesse = $(this).attr("data-groesse");
        var src = $(this).find("img").attr("src");
        if (colorid == thisFarbe && sizeid == thisGroesse) {
          $('.product-images .images').append('<div class="image-container"><img src="' + src + '" /></div>');
          $('.product-images .thumbnails').append('<div class="thumbnail-container"><img src="' + src + '" /></div>');
        }
      });
      $('.product-images .images > .image-container:first-child').addClass("active");
      $('.product-images .thumbnails > .thumbnail-container:first-child').addClass("active");
    });

    $(document).on("click", ".swatch-attribute.color_name .swatch-attribute-options > .swatch-option", function () {
      var colorid = $(this).attr("option-id");
      var sizeid = $('.swatch-attribute.size .swatch-attribute-options > .swatch-option.selected').attr("option-id");
      if (!sizeid) {
        $('.swatch-attribute.size .swatch-attribute-options > .swatch-option').each(function (index, element) {
          if (!$(element).hasClass("disabled")) {
            sizeid = $(element).attr('option-id');
            return false;
          }
        });
      }
      $('.product-images .images').html("");
      $('.product-images .thumbnails').html("");
      $('#ProductImages div').each(function () {
        var thisFarbe = $(this).attr("data-farbe");
        var thisGroesse = $(this).attr("data-groesse");
        var src = $(this).find("img").attr("src");
        if (colorid == thisFarbe && sizeid == thisGroesse) {
          $('.product-images .images').append('<div class="image-container"><img src="' + src + '" /></div>');
          $('.product-images .thumbnails').append('<div class="thumbnail-container"><img src="' + src + '" /></div>');
        }
      });
      $('.product-images .images > .image-container:first-child').addClass("active");
      $('.product-images .thumbnails > .thumbnail-container:first-child').addClass("active");
    });






    body.on("click", ".thumbnail-container", function () {
      $(".thumbnail-container").removeClass("active");
      $(this).addClass("active");
      var i = 0;
      $('.product-images .thumbnails .thumbnail-container').each(function () {
        if ($(this).hasClass("active")) {
          var width = $('.product-images .images div:first-of-type').outerWidth();
          var newMarginLeft = parseInt(i) * parseInt(width);
          $('.product-images .images .image-container:first').css("margin-left", "-" + newMarginLeft + "px");
          $('.product-images .images .image-container').removeClass("active");
          var a = i + 1;
          $('.product-images .images .image-container:nth-child(' + a + ')').addClass("active");
          return false;
        }
        i++;
      });
    });



    body.on("click", ".product-images .arrows .right", function () {
      var width = $('.product-images .images div:first-of-type').outerWidth();
      if ($('.product-images .images .image-container.active').next().length > 0) {
        $('.product-images .images .image-container.active').removeClass("active").next().addClass("active");
        var MarginLeft = $('.product-images .images .image-container:first').css("margin-left");
        var newMarginLeft = parseInt(MarginLeft) - width;
        $('.product-images .images .image-container:first').css("margin-left", newMarginLeft + "px");
      }
    });

    body.on("click", ".product-images .arrows .left", function () {
      var width = $('.product-images .images div:first-of-type').outerWidth();
      if ($('.product-images .images .image-container.active').prev().length > 0) {
        $('.product-images .images .image-container.active').removeClass("active").prev().addClass("active");
        var MarginLeft = $('.product-images .images .image-container:first').css("margin-left");
        var newMarginLeft = parseInt(MarginLeft) + width;
        $('.product-images .images .image-container:first').css("margin-left", newMarginLeft + "px");
      }
    });






























    if ($('.product-images-container').length > 0) {
      /* SWIPE EFFEKT FÜR SLIDER PDP PAGE */
      var myElement = document.getElementsByClassName("product-images-container");
      myElement[0].addEventListener("touchstart", SliderCstartTouch, false);
      myElement[0].addEventListener("touchmove", SliderCmoveTouch, false);
      // Swipe Up / Down / Left / Right
      var initialX = null;
      var initialY = null;
      function SliderCstartTouch(e) {
        initialX = e.touches[0].clientX;
        initialY = e.touches[0].clientY;
      };
      function SliderCmoveTouch(e) {
        if (initialX === null) {
          return;
        }
        if (initialY === null) {
          return;
        }
        var currentX = e.touches[0].clientX;
        var currentY = e.touches[0].clientY;
        var diffX = initialX - currentX;
        var diffY = initialY - currentY;
        if (Math.abs(diffX) > Math.abs(diffY)) {
          // sliding horizontally
          if (diffX > 0) {
            // swiped left
            var width = $('.product-images .images div:first-of-type').outerWidth();
            if ($('.product-images .images .image-container.active').next().length > 0) {
              $('.product-images .images .image-container.active').removeClass("active").next().addClass("active");
              var MarginLeft = $('.product-images .images .image-container:first').css("margin-left");
              var newMarginLeft = parseInt(MarginLeft) - width;
              $('.product-images .images .image-container:first').css("margin-left", newMarginLeft + "px");
              console.log("links");
            }
          } else {
            // swiped right
            var width = $('.product-images .images div:first-of-type').outerWidth();
            if ($('.product-images .images .image-container.active').prev().length > 0) {
              $('.product-images .images .image-container.active').removeClass("active").prev().addClass("active");
              var MarginLeft = $('.product-images .images .image-container:first').css("margin-left");
              var newMarginLeft = parseInt(MarginLeft) + width;
              $('.product-images .images .image-container:first').css("margin-left", newMarginLeft + "px");
            }
          }
          initialX = null;
          initialY = null;
          e.preventDefault();
        } else {
          // sliding vertically
          if (diffY > 0) {
            // swiped up
            console.log("swiped up");
          } else {
            // swiped down
            console.log("swiped down");
          }
        }

      };
    }





    if ($('.image-slider').length > 0) {
      /* SWIPE EFFEKT FÜR SLIDER LANDINGPAGE OBEN */
      var myElement = document.getElementsByClassName("image-slider");
      myElement[0].addEventListener("touchstart", SliderAstartTouch, false);
      myElement[0].addEventListener("touchmove", SliderAmoveTouch, false);
      // Swipe Up / Down / Left / Right
      var initialX = null;
      var initialY = null;
      function SliderAstartTouch(e) {
        initialX = e.touches[0].clientX;
        initialY = e.touches[0].clientY;
      };
      function SliderAmoveTouch(e) {
        if (initialX === null) {
          return;
        }
        if (initialY === null) {
          return;
        }
        var currentX = e.touches[0].clientX;
        var currentY = e.touches[0].clientY;
        var diffX = initialX - currentX;
        var diffY = initialY - currentY;
        if (Math.abs(diffX) > Math.abs(diffY)) {
          // sliding horizontally
          if (diffX > 0) {
            // swiped left
            $('.image-slider').find(".next.arrow").click();
          } else {
            // swiped right
            $('.image-slider').find(".prev.arrow").click();
          }
          initialX = null;
          initialY = null;
          e.preventDefault();
        } else {
          // sliding vertically
          if (diffY > 0) {
            // swiped up
            console.log("swiped up");
          } else {
            // swiped down
            console.log("swiped down");
          }
        }

      };
    }





    /** CHECKOUT ÜBERPRÜFEN
     * Überprüft die Checkout-Seite und nimmt je nach aktueller Ansicht verschiedene Anpassungen vor
     * Diese betreffen sowohl das Layout als auch die Funktionalitöt von Versand- und Zahlungsmethoden
     * Da der Checkout oft zeitverzögert lädt, läuft die Funktion bis zu ihrem Abschluss in einem Intervall
     */

    function prepareCheckout() {
      if ($("#checkout-loader").is(":visible") || $(".loading-mask").is(":visible")) {
        log("checking checkout page");
        return;
      }

      log("preparing checkout page");
      clearInterval(checkoutInterval);

      setTimeout(function () {
        checkoutPage.find("input").removeAttr("placeholder");
        checkoutPage.find("#shipping").addClass("hidden");

        // LOGIN
        if ($(".opc-progress-bar-item:nth-of-type(1)").hasClass("_active")) {
          log("prepared login step");
          preparedCheckoutSteps.login = true;

          if ($(".already-logged-in").is(":visible")) {
            window.location.href = window.location.href.slice(0, -15) + "#shipping";
            checkoutInterval = setInterval(prepareCheckout, 100);
          }

          // SHIPPING
        } else if ($(".opc-progress-bar-item:nth-of-type(2)").hasClass("_active")) {
          log("prepared shipping step");
          addShippingMethodLabels(1000);
          preparedCheckoutSteps.shipping = true;
          checkoutPage.find("#shipping").removeClass("hidden");
          checkoutPage.find(".opc-summary-wrapper .required-fields").insertAfter("#opc-shipping_method .actions-toolbar > .primary");
          checkoutPage.find(".field[name='shippingAddress.telephone'] input").attr("type", "number");
          body.find("footer .shipping-providers section." + shop).insertAfter("#opc-shipping_method .step-title");

          $('#co-shipping-form').find('select[name="country_id"],input[name="postcode"],input[name="city"]').change(function () {
            var countryId = $('#co-shipping-form').find('select[name="country_id"]').val();
            requirejs([
              'Magento_Checkout/js/model/quote',
              'Magento_Checkout/js/model/shipping-rate-registry'
            ], function (mainQuote, rateReg) {
              var address = mainQuote.shippingAddress();
              address.countryId = countryId;
              rateReg.set(address.getKey(), null);
              rateReg.set(address.getCacheKey(), null);
              mainQuote.shippingAddress(address);

              for (let i = 1000; i < 10000; i += 1000) {
                addShippingMethodLabels(i);
              }
            });
          });

          // PAYMENT
        } else if ($(".opc-progress-bar-item:nth-of-type(3)").hasClass("_active")) {
          preparePaymentStep();

          // CONFIRM
        } else if ($(".opc-progress-bar-item:nth-of-type(4)").hasClass("_active") && !preparedCheckoutSteps.confirm) {
          log("prepared confirm step");

          if (!preparedCheckoutSteps.shipping || !preparedCheckoutSteps.payment) {
            window.location.href = window.location.href.slice(0, -8) + "#login_register";
            window.location.reload();
          }

          preparedCheckoutSteps.confirm = true;
          checkoutPage.find(".opc-summary-wrapper").appendTo("#confirm .right-column");
          checkoutPage.find("#confirm .block.items-in-cart").detach().appendTo("#confirm .left-column");
          checkoutPage.find("#confirm .opc-block-shipping-information").detach().insertAfter("#confirm .right-column > .title");

          setTimeout(function () {
            body.find(".totals-tax").addClass("hidden");
            body.find(".grand.totals.excl").addClass("hidden");
          }, 1000);

          setTimeout(function () {
            body.find(".totals-tax").addClass("hidden");
          }, 2000);

          if (lang === "de") {
            $(".actions-toolbar-trigger button").text("Bestellung aufgeben");
          }

          $("#confirm .button.action.primary").on("click", function () {
            log("order submitted");
            $("#payment .actions-toolbar-trigger button").trigger("click");
            // $(".payment-method .actions-toolbar button").trigger("click");
          });

          // DONE
        } else if ($(".opc-progress-bar-item:nth-of-type(5)").hasClass("_active")) {
          log("prepared done step");
          preparedCheckoutSteps.done = true;
        } else {
          log("nothing to prepare");
        }
      }, 10);
    }


    /** ZAHLUNGSMETHODEN ÜBERPRÜFEN
     * Da der Schritt zur Auswahl der Zahlungsmethoden am komplexesten ist, wurde er in eine eigene Funktion ausgelagert
     * Es wird zunächst das Layout angepasst, dann die Logos der Zahlungsanbieter geladen und am Ende die Summen angepasst
     * Ausserdem wird wie bei den Versandmethoden die notwendige Funktionalität beim Auswählen von Zahlungsmethoden hinzugefügt
     */

    function preparePaymentStep() {
      clearInterval(paymentStepInterval);

      checkoutPage.find("input").removeAttr("placeholder");
      checkoutPage.find("#shipping").addClass("hidden");

      if (preparedCheckoutSteps.payment) {
        log("prepared payment step again");
        checkoutPage.find("aside").addClass("visible");
        checkoutPage.find(".opc-summary-wrapper").addClass("visible");
        return;
      }

      log("prepared payment step for the first time");
      preparedCheckoutSteps.payment = true;
      checkoutPage.find("#block-discount-heading").trigger("click");
      checkoutPage.find("#block-discount-heading").addClass("init").css("pointer-events", "none");
      checkoutPage.find(".checkout-billing-address").detach().insertBefore("#checkout-payment-method-load");
      checkoutPage.find(".opc-summary-wrapper").insertAfter("#checkout-step-payment .discount-code").addClass("visible");
      checkoutPage.find(".opc-summary-wrapper .billing-address-title").detach().insertBefore(".billing-address-same-as-shipping-block");
      checkoutPage.find("#checkout-step-payment .discount-code").detach().insertAfter("#checkout-step-payment .checkout-billing-address");
      checkoutPage.find(".opc-summary-wrapper .required-fields").insertAfter("#checkout-step-payment > form > .actions-toolbar > .primary");
      checkoutPage.find("aside").addClass("visible");

      setTimeout(function () {
        body.find(".totals-tax").addClass("hidden");
        body.find(".grand.totals.excl").addClass("hidden");


      }, 1000);

      setTimeout(function () {
        body.find(".totals-tax").addClass("hidden");
      }, 2000);

      checkoutPage.on("click", ".billing-address-same-as-shipping-block", function () {
        $("#mp-billing-address-title").toggleClass("visible");
      });

      $('div.payment-method').click(function () {
        var paymentMethodCode = $(this).find('input[name="payment[method]"]').val();
        requirejs(['Magento_Checkout/js/model/quote'], function (quote) {
          quote.setPaymentMethod(paymentMethodCode);
        });
      });
    }



    /** LOGOS DER VERSANDANBIETER EINFÜGEN
     * Fügt im zweiten Schritt des Checkouts Labels zu den einzelnen Versandmethoden hinzu
     * Diese beinhalten die Häkchen, die im Frontend beim Klick auf eine Methode angezeigt werden
     * Da das Layout nach jedem Klick neu geladen wird, wird auch diese Funktion jedes Mal wieder aufgerufen
     */

    function addShippingMethodLabels(timeout) {
      log("add shipping method labels " + timeout);
      setTimeout(function () {
        checkoutPage.find(".table-checkout-shipping-method .col-method label").remove();
        $("<label></label>").insertAfter(".table-checkout-shipping-method .col-method input");
      }, timeout);
    }


    /** PAYMENT STEP ÜBERPRÜFEN
     * Überpüft nach dem Klick auf den Weiter-Button im zweiten Schritt des Checkouts, wann der dritte Schritt geladen wurde
     * Ist notwendig, da insbesondere der dritte Schritt des Checkouts, der die Zahlungsmethoden beinhaltet, oft zeitverzögert lädt
     * Läuft so lange in einem Intervall, bis der dritte Schritt geladen wurde, und ruft dann die Funktion zur Anpassung auf
     */

    function checkPaymentStep() {
      log("checking payment step");

      if ($(".opc-progress-bar-item:nth-of-type(3)").hasClass("_active")) {
        preparePaymentStep();
        clearInterval(paymentStepInterval);
      }
    }



    /** PRODUKTBESCHREIBUNG UND DETAILS ANPASSEN
     * Klickt man auf eine Option bei konfugurierbaren Produkten, wird die ID des konfigurierten Produkts bestimmt
     * Die Produktbeschreibungen und Details aller konfigurierbaren Produkte liegen bereits versteckt auf der Produktdetailseite
     * Die passenden Einträge werden beim Klick auf die entsprechenden Optionen lediglich an die richtige Stelle verschoben und sichtbar gemacht
     */

    function changeProductDescription() {
      let selected_options = {};

      $('div.swatch-attribute').each(function (k, v) {
        let attribute_id = $(v).attr('attribute-id');
        let option_selected = $(v).attr('option-selected');
        if (!attribute_id || !option_selected) { return; }
        selected_options[attribute_id] = option_selected;
      });

      let product_id_index = $('[data-role=swatch-options]').data('mage-SwatchRenderer').options.jsonConfig.index;
      let found_ids = [];

      $.each(product_id_index, function (product_id, attributes) {
        let productIsSelected = function (attributes, selected_options) {
          return _.isEqual(attributes, selected_options);
        }
        if (productIsSelected(attributes, selected_options)) {
          found_ids.push(product_id);
        }
      });

      let id = found_ids[0];

      body.find(".overview .description").html($(".child-id-" + id + " .description").html());
      body.find(".overview .details .content").html($(".child-id-" + id + " .details").html());
      body.find(".overview .short-description .content").html($(".child-id-" + id + " .short-description").html());

      log("changed product description");
      log("selected simple product id is " + id);
    }



    /** BILD IM BILDER-SLIDER WECHSELN
     * Ruft dasjenige Bild im Slider auf, das in dem übergebenen element deklariert ist
     * Kann entweder beim Klick auf ein Steuerelement oder automatisch aufgerufen werden
     */

    function changeImageSlide(element) {
      let nextImageSlide;
      let slides = imageSlider.find(".slide");

      if (element === undefined || element.hasClass("next")) {
        nextImageSlide = imageSlide + 1;
      } else if (element.hasClass("prev")) {
        nextImageSlide = imageSlide - 1;
      } else {
        nextImageSlide = element.data("slide");
      }

      if (nextImageSlide > slides.length) {
        imageSlide = 1;
      } else if (nextImageSlide === 0) {
        imageSlide = slides.length;
      } else {
        imageSlide = nextImageSlide;
      }

      slides.removeClass("active");
      body.find(".loaders .animate").removeAttr("style");
      imageSlider.find(".dot").removeClass("transparent");
      imageSlider.find(".slide" + imageSlide).addClass("active");
      imageSlider.find(".dot" + imageSlide + ", .spinner-holder-two").addClass("transparent");
      imageSlider.find(".loader" + imageSlide + " .spinner-holder-two").removeClass("transparent").css("transform", "rotate(0deg)");

      if (element !== undefined) {
        imageSliderInterval = setInterval(changeImageSlide, imageSlideTime);
      }
    }


    /** FILTER ANZEIGEN ODER AUSBLENDEN
     * Öffnet oder schliesst die Filterübersicht beim Klick auf den Filter-Button auf der Katalogseite
     * Scrollt an die entsprechende Stelle in der mobilen Version, da der Button dort immer mitläuft
     */

    function toggleCategoryFilter() {
      let filterBlock = $(".block.filter");
      let filterButton = $(".toggle-category-filter");

      if (!filterBlock.hasClass("open")) {
        filterBlock.addClass("open");
        filterButton.addClass("active");

        if (isMobile) {
          $("html, body").animate({ scrollTop: 640 }, "slow");
        }
      } else {
        filterBlock.removeClass("open");
        filterButton.removeClass("active");
      }
    }





    /** BEZEICHNUNG DER WÄHRUNG ANPASSEN
     * Ersetzt die Bezeichnung der angezeigten Währung
     * Betrifft lediglich die Anzeige im Header der Seite
     */

    function replaceCurrencyName() {
      let currencyContainer = $("#switcher-currency-trigger-nav span");

      if (currencyContainer.text().includes("EUR")) {
        currencyContainer.text("EUR");
      } else if (currencyContainer.text().includes("CHF")) {
        currencyContainer.text("CHF");
      } else if (currencyContainer.text().includes("DKK")) {
        currencyContainer.text("DKK");
      } else if (currencyContainer.text().includes("PLN")) {
        currencyContainer.text("PLN");
      } else if (currencyContainer.text().includes("SEK")) {
        currencyContainer.text("SEK");
      }

      currencyContainer.addClass("visible");
    }



    /** OVERLAYS POSITIONIEREN
     * Überdeckt gewisse Seitenelemente mit unsichtbaren Overlays
     * Somit werden mit den Elementen verbundene Magento-Funktionen unterbunden
     * Stattdessen sind die Overlays mit eigenen Funktionen verknüpft
     */

    function positionOverlays() {
      let languageSwitcher = $(".switcher-language");
      let currencySwitcher = $(".switcher-currency");

      languageOverlay.css({ "top": languageSwitcher.css("top"), "right": languageSwitcher.css("right") });
      currencyOverlay.css({ "top": currencySwitcher.css("top"), "right": currencySwitcher.css("right") });

      languageOverlay.appendTo("#store\\.settings");
      currencyOverlay.appendTo("#store\\.settings");
      minicartOverlay.appendTo(".nav-sections .nav-sections-items");
    }



    /** NEWSLETTER-ANMELDUNG EINFÜGEN
     * Fügt das Formular zur Newsletter-Anmeldung an der passenden Stelle im Footer der Seite ein
     * Der restliche Inhalt des Footers kann im Backend-Block footer_areas verwaltet werden
     */

    function moveNewsletterSubscription() {
      body.find(".block.newsletter").appendTo(".newsletter.section").removeClass("hidden");
    }



    /** STORES-LINK ZUR NAVIGATION EINFÜGEN
     * Fügt einen Link zum Storefinder als letztes Element in die Hauptnavigation ein
     * Benötigt zumindest im Entwicklermodus einen Timeout, um tatsächlich eingefügt zu werden
     */

    function addStoresLink() {
      setTimeout(function () {
        body.find(".nav-sections .navigation > .ui-menu").append("<li class='level0 category-item last level-top ui-menu-item'><a href='stores.html'><span>Stores</span></a></li>");
      }, 1000);
    }



    /** REGION CHOOSER INITIALISIEREN
     * Passt die aktiven Einstellungen in dem Overlay an, in dem Sprache und Währung gewählt werden können
     * Verwendet die mit setChosenCountry() und setChosenLang() festgelegten Variablen chosenCountry und chosenLang
     */

    function initRegionChooser() {
      if ($(".switcher-currency").length === 0) {
        $("#switcher-language-nav").parent().prepend("<div class=\"switcher currency switcher-currency\" id=\"switcher-currency-nav\"><strong class=\"label switcher-label\"><span>Currency</span></strong><div class=\"actions dropdown options switcher-options\"><div class=\"action toggle switcher-trigger\" id=\"switcher-currency-trigger-nav\"><strong class=\"language-EUR\"><span class=\"visible\">EUR</span></strong></div><ul class=\"dropdown switcher-dropdown\" data-target=\"dropdown\" aria-hidden=\"true\"><li class=\"currency-EUR switcher-option\"><a href=\"#\">EUR - Euro</a></li></ul></div></div>");
      }

      // body.find(".region-chooser ." + chosenCountry).addClass("active");
      // body.find(".region-chooser .languages select").find("option[value='" + chosenLang + "']").attr("selected",true);

      updateRegionLangAndCurrency();
    }



    /** REGION CHOOSER AKTUALISIEREN
     * Passt die aktiven Einstellungen in dem Overlay an, in dem Sprache und Währung gewählt werden können
     * Wird beim Laden der Seite aufgerufen oder nachdem das Land im Region Chooser verändert wurde
     */

    function updateRegionLangAndCurrency() {
      let regionChooser = body.find(".region-chooser");

      regionChooser.find("option").removeClass("hidden");
      regionChooser.find(".currencies option").removeClass("visible").addClass("hidden");

      switch (chosenCountry) {
        case "at":
        case "ch":
        case "de":
          regionChooser.find(".languages option[value='de']").attr("selected", "selected");
          break;
        default:
          regionChooser.find(".languages option[value='de']").addClass("hidden");
          regionChooser.find(".languages option[value='en']").attr("selected", "selected");
          break;
      }

      switch (chosenCountry) {
        case "at":
        case "de":
        case "eu":
        case "be":
        case "it":
        case "fr":
        case "nl":
          regionChooser.find(".currencies option[value='eur']").addClass("visible").attr("selected", "selected");
          break;
        case "ch":
          regionChooser.find(".currencies option[value='chf']").addClass("visible").attr("selected", "selected");
          break;
        case "dk":
          regionChooser.find(".currencies option[value='dkk']").addClass("visible").attr("selected", "selected");
          break;
        case "pl":
          regionChooser.find(".currencies option[value='pln']").addClass("visible").attr("selected", "selected");
          break;
        case "se":
          regionChooser.find(".currencies option[value='sek']").addClass("visible").attr("selected", "selected");
          break;
      }

      chosenLang = regionChooser.find(".languages option:selected").val();
    }



    /** ANIMATIONEN AUSFÜHREN
     * Überprüft beim Scrollen, ob animierbare Elemente sichtbar werden
     * Führt bei Bedarf die zugehörige Animation aus
     */

    function doAnimations() {
      let offset = $(window).scrollTop() + $(window).height(), animatables = $('.animatable');

      if (animatables.length === 0) {
        $(window).off('scroll', doAnimations);
      }

      animatables.each(function () {
        let animatable = $(this);

        if ((animatable.offset().top + animatable.height() / 3) < offset) {
          animatable.removeClass('animatable').addClass('animated');
        }
      });
    }





    /** REGION CHOOSER AKTUALISIEREN
     * Passt die aktiven Einstellungen in dem Overlay an, in dem Sprache und Währung gewählt werden können
     * Wird beim Laden der Seite aufgerufen oder nachdem das Land im Region Chooser verändert wurde
     */

    function setMode() {
      if (baseUrl.includes("local")) {
        return "local";
      } else if (baseUrl.includes("staging")) {
        return "stage";
      } else {
        return "live";
      }
    }



    /** SPRACHE ERMITTELN
     * Überprüft die URL und setzt in Abhängigkeit davon die aktuelle Sprache des Shops
     * Muss in der Live-Umgebung an die entsprechende Live-URL angepasst werden
     */

    function setChosenLang() {
      if (mode === "live" && baseUrl.length > 30) {
        return baseUrl.substring(30, 32);
      } else if (mode === "stage" && baseUrl.length > 42) {
        return baseUrl.substring(42, 44);
      } else {
        return "de";
      }
    }



    /** LAND ERMITTELN
     * Überprüft die URL und setzt in Abhängigkeit davon das aktuelle Land des Shops
     * Muss in der Live-Umgebung an die entsprechende Live-URL angepasst werden
     */

    function setChosenCountry() {
      if (mode === "live" && baseUrl.length > 30) {
        return baseUrl.substring(33, 35);
      } else if (mode === "stage" && baseUrl.length > 42) {
        return baseUrl.substring(45, 47);
      } else {
        return "ch";
      }
    }



    /** IN DIE ENTWICKLERKONSOLE LOGGEN
     * Loggt beim Aufruf Nachrichten in die Entwicklerkonsole
     * Wird nur in der lokalen oder Stage-Umgebung aufgerufen
     */

    function log(message) {
      if (mode === "local" || mode === "stage") {
        console.log(message);
      }
    }





    $(document).on("click", ".catalog-product-view #md-zoom-icon", function () {
      $(".catalog-product-view .fotorama__stage__frame.fotorama__active .fotorama__img").clone().appendTo("#fullscreen-zoom");
      $("#fullscreen-zoom").addClass("fullscreen");
    });

    $(document).on("click", "#fullscreen-zoom", function () {
      $(this).removeClass("fullscreen");
      $(this).empty();
    });











  });

  function guestwishlistCountermd() {
    if ($(".guestwishlist .counter.qty").length > 0) {

      if ($('.guestwishlist .counter.qty').html() == '0') {

        $(".guestwishlist .counter.qty").html('');

      }

    }
  }

  var intervalId = window.setInterval(guestwishlistCountermd, 50);



  $(document).ready(function () {

    $(function () {

      $('.home-message p.slide:gt(0)').hide();
      setInterval(function () {
        $('.home-message p.slide:first-child').fadeOut(2000).next('p.slide').fadeIn(2000)
          .end().appendTo('.home-message');
      }, 7000);

    });
  });






















  $(document).ready(function () {
    if ($('.catalogsearch-result-index .results .product-items').length > 0) {
      $('.catalogsearch-result-index .page-title-wrapper').hide();
    }
  });


  $(document).ready(function () {
    $(document).on("click", '.overview h3', function () {
      $(this).next('.content').slideToggle();
      $(this).toggleClass("open");
    });
  });







  $(document).on("focus", ".swatch-select.size", function () {
    $(".swatch-select.size option[value='0']").hide(0);
  });
  $(document).on("focusout", ".swatch-select.size", function () {
    $(".swatch-select.size option[value='0']").show(0);
  });

  /*
  $(document).on("click", ".swatch-option.color", function(){
    $('.swatch-select.size option').each(function(){ 
      if($(this).attr("option-id")!="0"){
        $(this).removeClass("alreadychanged");
        var text = $(this).html();
        var cut = text.split(" ");
        var newtext = cut[0];
        $(this).html(newtext);
      }  
    });  
  });
  
  
  function AddSaledOut() {
    if($('.swatch-select.size').length>0){ 
      $('.swatch-select.size option').each(function(){ 
        if($(this).attr("option-id")!="0"){
          if($(this).hasClass("disabled") && !$(this).hasClass("alreadychanged")){ 
            var text = $(this).text(); 
            var newtext = text+ " (" +$t('sold out') +")"; 
            $(this).text(newtext).addClass("alreadychanged");    
          }
        }
      });
      //clearInterval(AddSaledOutInterval);       
    }  
  }  
  var AddSaledOutInterval = setInterval(AddSaledOut, 50);
  
  */

  function RemoveWrongThumbsOutOffSlider() {
    if ($('.catalog-product-view').length > 0) {
      var src = $(".swatch-option.color.selected img").attr("src");
      var cut = src.split("/");
      var length = cut.length - 1;
      var lastpart = cut[length]; // url vorn abtrennen, damit bildname übrig bleibt
      var cut = lastpart.split("_");
      var identswatch = cut[0] + "_" + cut[1]; // bildkennung ohne url und ohne dateiendung oder sonstigem
      var found = [];
      $(".fotorama__nav__shaft img").each(function () {
        var src = $(this).attr("src");
        var cut = src.split("/");
        var length = cut.length - 1;
        var lastpart = cut[length]; // url vorn abtrennen, damit bildname übrig bleibt
        var cut = lastpart.split("_");
        var identslide = cut[0] + "_" + cut[1]; // bildkennung ohne url und ohne dateiendung oder sonstigem
        if (identswatch != identslide || found.includes(src)) {
          $(this).parent().parent().remove();
        }
        found.push(src);
      });
    }
  }
  //  var RemoveWrongThumbsOutOffSliderInterval = setInterval(RemoveWrongThumbsOutOffSlider, 50);








});
