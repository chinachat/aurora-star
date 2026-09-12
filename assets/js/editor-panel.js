/**
 * Aurora Star 区块编辑器侧栏面板
 * - 「隐藏本文浮动目录」开关（对应 post meta _aurora_star_disable_toc）
 *
 * 经典编辑器下的等价控件见 inc/toc.php 的元框。
 * 不依赖构建步骤，直接使用 window.wp 上的公开 API。
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.data || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var CheckboxControl = wp.components.CheckboxControl;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var __ = wp.i18n ? wp.i18n.__ : function (text) { return text; };

	// 缺少面板组件（老版本 WP）时静默退出，不影响编辑器。
	if (!PluginDocumentSettingPanel || !CheckboxControl || !useSelect || !useDispatch) {
		return;
	}

	var META_KEY = '_aurora_star_disable_toc';

	function AuroraStarPostPanel() {
		var disabled = useSelect(function (select) {
			var meta = select('core/editor').getEditedPostAttribute('meta') || {};
			if (!Object.prototype.hasOwnProperty.call(meta, META_KEY)) {
				return false;
			}
			return !!meta[META_KEY];
		}, []);

		var editPost = useDispatch('core/editor').editPost;

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'aurora-star-post-options',
				title: __('Aurora Star 文章选项', 'aurora-star'),
				className: 'aurora-star-post-options-panel'
			},
			el(CheckboxControl, {
				label: __('隐藏本文浮动目录', 'aurora-star'),
				checked: disabled,
				onChange: function (checked) {
					var meta = {};
					meta[META_KEY] = !!checked;
					editPost({ meta: meta });
				}
			})
		);
	}

	wp.plugins.registerPlugin('aurora-star-post-options', {
		render: AuroraStarPostPanel
	});
})(window.wp);
