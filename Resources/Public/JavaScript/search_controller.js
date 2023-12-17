
function SearchController() {
    var _this = this;

    _this.ajaxType = 7383;

    this.init = function() {
        jQuery("body").delegate("a.meilisearch-ajaxified", "click", _this.handleClickOnAjaxifiedUri);
    };

    this.handleClickOnAjaxifiedUri = function() {
        var clickedLink = jQuery(this);

        var meilisearchContainer = clickedLink.closest(".tx_meilisearch");
        var meilisearchParent = meilisearchContainer.parent();

        var loader = jQuery("<div class='tx-meilisearch-loader'></div>");
        var uri = clickedLink.uri();

        meilisearchParent.append(loader);
        uri.addQuery("type", _this.ajaxType);

        jQuery.get(
            uri.href(),
            function(data) {
                meilisearchContainer = meilisearchContainer.replaceWith(data);
                _this.scrollToTopOfElement(meilisearchParent, 50);
                jQuery("body").trigger("tx_meilisearch_updated");
                loader.fadeOut().remove();
                history.replaceState({}, null, uri.removeQuery("type").href());
            }
        );
        return false;
    };

    this.scrollToTopOfElement = function(element, deltaTop) {
        jQuery('html, body').animate({
            scrollTop: (element.offset().top - deltaTop) + 'px'
        }, 'slow');
    };

    this.setAjaxType = function(ajaxType) {
        _this.ajaxType = ajaxType;
    };
}

jQuery(document).ready(function() {
    var meilisearchSearchController = new SearchController();
    meilisearchSearchController.init();

    if(typeof meilisearchSearchAjaxType !== "undefined") {
        meilisearchSearchController.setAjaxType(meilisearchSearchAjaxType);
    }
});
