/*!
 * Import Columns Selector Validation Module.
 *
 * @author : lonelythinker
 * @email : 710366112@qq.com
 * @homepage : www.lonelythinker.cn
 * @date：2016年4月23日
 */
(function ($) {
    "use strict";

    var ImportColumns = function (element, options) {
        var self = this;
        self.$element = $(element);
        self.options = options;
        self.listen();
    };

    ImportColumns.prototype = {
        constructor: ImportColumns,
        listen: function () {
            var self = this, $el = self.$element, $tog = $el.find('input[name="import_columns_toggle"]');
            $el.off('click').on('click', function (e) {
                e.stopPropagation();
            });
            $tog.off('change').on('change', function () {
                var checked = $tog.is(':checked');
                $el.find('input[name="import_columns_selector[]"]').prop('checked', checked);
            });
        }
    };

    //ImportColumns plugin definition
    $.fn.importcolumns = function (option) {
        var args = Array.apply(null, arguments);
        args.shift();
        return this.each(function () {
            var $this = $(this),
                data = $this.data('importcolumns'),
                options = typeof option === 'object' && option;

            if (!data) {
                $this.data('importcolumns', (data = new ImportColumns(this,
                    $.extend({}, $.fn.importcolumns.defaults, options, $(this).data()))));
            }

            if (typeof option === 'string') {
                data[option].apply(data, args);
            }
        });
    };

    $.fn.importcolumns.defaults = {};

})(window.jQuery);