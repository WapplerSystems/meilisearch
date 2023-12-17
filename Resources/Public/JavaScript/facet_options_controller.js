/**
 * The Controller. Controller responds to user actions and
 * invokes changes on the model.
 */
function OptionFacetController() {
    var _this = this;

    this.init = function () {
        _this.initToggle();
        _this.initFilter();
    };

    this.initToggle = function () {

        jQuery('.tx-meilisearch-facet-hidden').hide();
        jQuery('a.tx-meilisearch-facet-show-all').click(function() {
            if (jQuery(this).parent().siblings('.tx-meilisearch-facet-hidden:visible').length == 0) {
                jQuery(this).parent().siblings('.tx-meilisearch-facet-hidden').show();
                jQuery(this).text(jQuery(this).data('label-less'));
            } else {
                jQuery(this).parent().siblings('.tx-meilisearch-facet-hidden').hide();
                jQuery(this).text(jQuery(this).data('label-more'));
            }

            return false;
        });
    }

    this.initFilter = function () {
        filterableFacets = jQuery(".facet-filter-box").closest('.facet');
        filterableFacets.each(
            function () {
                var searchBox = jQuery(this).find('.facet-filter-box');
                var searchItems = jQuery(this).find('.facet-filter-item');
                searchBox.on("keyup", function() {
                    var value = searchBox.val().toLowerCase();
                    searchItems.each(function() {
                        var filteredItem = jQuery(this);
                        filteredItem.toggle(filteredItem.text().toLowerCase().indexOf(value) > -1)
                    });
                });
            }
        );
    }
}

jQuery(document).ready(function () {
    var optionsController = new OptionFacetController();
    optionsController.init();

    jQuery("body").on("tx_meilisearch_updated", function() {
        optionsController.init();
    });
});
