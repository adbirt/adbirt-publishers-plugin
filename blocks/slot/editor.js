/* Editor side of the Adbirt Ad Slot block. No build step: plain wp.* globals. */
(function (wp) {
  var el = wp.element.createElement;
  var __ = wp.i18n.__;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var TextControl = wp.components.TextControl;
  var Placeholder = wp.components.Placeholder;
  var SLOT_PATTERN = /^slot_[a-f0-9]{24}$/;

  wp.blocks.registerBlockType('adbirt/slot', {
    edit: function (props) {
      var slotId = props.attributes.slotId || '';
      var valid = SLOT_PATTERN.test(slotId);
      return el(
        'div',
        useBlockProps(),
        el(
          Placeholder,
          {
            icon: 'megaphone',
            label: __('Adbirt Ad Slot', 'adbirt-ads-display'),
            instructions: valid
              ? __('An ad from this slot shows here on your site.', 'adbirt-ads-display')
              : __('Paste a slot id from the Publisher section of your Adbirt dashboard.', 'adbirt-ads-display'),
          },
          el(TextControl, {
            label: __('Slot id', 'adbirt-ads-display'),
            value: slotId,
            placeholder: 'slot_...',
            help: slotId && !valid ? __('A slot id looks like slot_ followed by 24 letters and digits.', 'adbirt-ads-display') : undefined,
            onChange: function (value) {
              props.setAttributes({ slotId: String(value || '').trim() });
            },
          })
        )
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp);
