<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Crm\Activity\Provider\Visit;
use Bitrix\Crm\Conversion\LeadConversionConfig;
use Bitrix\Crm\Integration\Analytics\Dictionary;
use Bitrix\Crm\Integration\Market\Router;
use Bitrix\Crm\Integration\NotificationsManager;
use Bitrix\Crm\Integration\PullManager;
use Bitrix\Crm\Kanban\Helper;
use Bitrix\Crm\Kanban\ViewMode;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Settings\CounterSettings;
use Bitrix\Crm\Tour;
use Bitrix\Crm\Tour\RepeatSale\OnboardingPopup;
use Bitrix\Crm\UI\SettingsButtonExtender\SettingsButtonExtenderParams;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\Date;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;

/**
 * @var array $arParams
 * @var array $arResult
 * @global CMain $APPLICATION
 * @var CBitrixComponentTemplate $this
 */

if (isset($arResult['ERROR']))
{
	ShowError($arResult['ERROR']);

	return;
}

Loc::loadMessages(__FILE__);

$this->addExternalCss('/bitrix/themes/.default/crm-entity-show.css');

$bodyClass = $APPLICATION->getPageProperty("BodyClass");
$APPLICATION->setPageProperty("BodyClass",
	($bodyClass ? $bodyClass." " : "").
	"no-all-paddings grid-mode pagetitle-toolbar-field-view crm-toolbar no-background"
);

$data = $arResult['ITEMS'];
$date = new Date;
$contactCenterUrl = Container::getInstance()->getRouter()->getContactCenterUrl();

// js extension reg
\CJSCore::registerExt('crm_common', array(
	'js' => array('/bitrix/js/crm/crm.js', '/bitrix/js/crm/common.js')
));
\CJSCore::registerExt('crm_activity_type', array(
	'js' => array('/bitrix/js/crm/activity.js')
));
\CJSCore::registerExt('crm_partial_entity_editor', array(
	'js' => array('/bitrix/js/crm/partial_entity_editor.js', '/bitrix/js/crm/dialog.js')
));
\CJSCore::registerExt('popup_menu', array(
	'js' => array('/bitrix/js/main/popup_menu.js')
));

Extension::load([
	'crm_common',
	'crm.kanban',
	'crm.kanban.sort',
	'crm_visit_tracker',
	'crm_activity_type',
	'crm_partial_entity_editor',
	'crm.entity-editor',
	'popup_menu',
	'currency',
	'intranet_notify_dialog',
	'marketplace',
	'sidepanel',
	'uf',
	'crm.badge',
	'ui.actionpanel',
	'ui.notification',
	'ui.design-tokens',
]);

include 'editors.php';

$isMergeEnabled = isset($arParams['PATH_TO_MERGE']) && $arParams['PATH_TO_MERGE'] !== '';
if ($isMergeEnabled)
{
	Extension::load(['crm.merger.batchmergemanager']);
}

$gridId = Helper::getGridId($arParams['ENTITY_TYPE_CHR']);

$entityTypeId = (int) $arParams['ENTITY_TYPE_INT'];

$isActivityLimitIsExceeded = (bool) ($data['activityLimitIsExceeded'] ?? false);

$showActivity = 'false';
if (!$isActivityLimitIsExceeded && CounterSettings::getInstance()->isEnabled())
{
	$showActivity = isset($arParams['SHOW_ACTIVITY']) && $arParams['SHOW_ACTIVITY'] === 'Y' ? 'true' : 'false';
}

$section = $arParams['EXTRA']['ANALYTICS']['c_section'] ?? null;
$subSection = $arParams['EXTRA']['ANALYTICS']['c_sub_section'] ?? null;

echo Tour\Permissions\AutomatedSolution::getInstance()
	->setEntityTypeId($entityTypeId)
	->build()
;

$repeatSaleEntityTypeIds = [\CCrmOwnerType::Deal, \CCrmOwnerType::Contact, \CCrmOwnerType::Company];
if (in_array($entityTypeId, $repeatSaleEntityTypeIds, true))
{
	print OnboardingPopup::getInstance()->setAnalytics([
		'c_section' => $section,
		'c_sub_section' => $subSection,
	])->build();
}

if (defined('AIR_SITE_TEMPLATE'))
{
	$isInIframe = \Bitrix\Main\Context::getCurrent()->getRequest()->getQuery('IFRAME') === 'Y';
	$this->setViewTarget($isInIframe ? 'above_pagetitle' : 'page_menu', 100);
		?><div class="crm-kanban-action-panel-container">
			<div class="crm-kanban-action-panel"><div></div></div><?
		?></div><?
	$this->endViewTarget();
}
?>

<!-- ========== ДАШБОРД СТАТИСТИКИ (ДЛЯ СМАРТ-ПРОЦЕССА ПО ПРОИЗВОДСТВЕННЫМ ЗАДАНИЯМ ID=1038) ========== -->
<?php if ($entityTypeId == 1038): ?>
<div class="production-stats-dashboard" style="
    display: flex;
    gap: 15px;
    margin: 0 20px 20px 20px;
    padding: 15px 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
">
    <div class="stat-card" style="flex: 1; text-align: center; padding: 10px;">
        <div class="stat-number" style="font-size: 28px; font-weight: bold; color: #a0a0a0;" id="prod-stat-total">0</div>
        <div class="stat-label" style="font-size: 12px; color: #535c69;">Всего заданий</div>
    </div>
    <div class="stat-card" style="flex: 1; text-align: center; padding: 10px;">
        <div class="stat-number" style="font-size: 28px; font-weight: bold; color: #ff9100;" id="prod-stat-in-work">0</div>
        <div class="stat-label" style="font-size: 12px; color: #535c69;">Подготовка к исполнению</div>
    </div>
    <div class="stat-card" style="flex: 1; text-align: center; padding: 10px;">
        <div class="stat-number" style="font-size: 28px; font-weight: bold; color: #1dc4e9;" id="prod-stat-quality">0</div>
        <div class="stat-label" style="font-size: 12px; color: #535c69;">На контроле качества</div>
    </div>
    <div class="stat-card" style="flex: 1; text-align: center; padding: 10px;">
        <div class="stat-number" style="font-size: 28px; font-weight: bold; color: #2fc94e;" id="prod-stat-ready">0</div>
        <div class="stat-label" style="font-size: 12px; color: #535c69;">Готово</div>
    </div>
</div>
<?php endif; ?>
<!-- ========== КОНЕЦ ДАШБОРДА ========== -->

<!-- ========== CSS ДЛЯ ПОДСВЕТКИ КАРТОЧЕК СО СТАДИЕЙ "НАРУШЕНИЕ СРОКОВ" ========== -->
<style>
/* Основная подсветка карточки */
.crm-kanban-item[data-stage-id="DT1038_8:UC_B00ZPF"] {
    background-color: #ffeeee !important;
    border-left: 4px solid #ff0000 !important;
    transition: all 0.2s ease;
}

/* Эффект при наведении */
.crm-kanban-item[data-stage-id="DT1038_8:UC_B00ZPF"]:hover {
    background-color: #ffe0e0 !important;
    transform: translateX(2px);
}

/* Жирный шрифт для заголовка */
.crm-kanban-item[data-stage-id="DT1038_8:UC_B00ZPF"] .crm-kanban-item-title {
    font-weight: bold !important;
    color: #cc0000 !important;
}

/* Иконка предупреждения перед названием */
.crm-kanban-item[data-stage-id="DT1038_8:UC_B00ZPF"] .crm-kanban-item-title::before {
    content: "⚠️ ";
    margin-right: 5px;
    font-weight: bold;
}
</style>
<!-- ========== КОНЕЦ CSS ПОДСВЕТКИ ========== -->

<div id="crm_kanban"></div>

<script>
	let Kanban;
	const ajaxHandlerPath = "<?= $this->getComponent()->getPath()?>/ajax.old.php";

	BX.ready(
		function()
		{
			"use strict";

			// ========== ЗАГРУЗКА СТАТИСТИКИ ДЛЯ СМАРТ-ПРОЦЕССА (ID=1038) ==========
			<?php if ($entityTypeId == 1038): ?>
			(function loadProductionStatistics() {
				console.log('Запрос статистики для смарт-процесса ID=1038...');
				
				BX.ajax.runAction('crm.api.item.list', {
					data: {
						entityTypeId: 1038,
						categoryId: 8,
						select: ['id', 'stageId'],
					}
				}).then(function(response) {
					if (!response.data || !response.data.items) {
						console.error('Неверный формат ответа от сервера:', response);
						return;
					}

					var items = response.data.items;
					var total = items.length;
					var preparation = 0;
					var client = 0;
					var success = 0;

					for (var i = 0; i < items.length; i++) {
						var stageId = items[i].stageId;
						if (stageId === 'DT1038_8:PREPARATION') {
							preparation++;
						} else if (stageId === 'DT1038_8:CLIENT') {
							client++;
						} else if (stageId === 'DT1038_8:SUCCESS') {
							success++;
						}
					}

					var totalEl = document.getElementById('prod-stat-total');
					var inWorkEl = document.getElementById('prod-stat-in-work');
					var qualityEl = document.getElementById('prod-stat-quality');
					var readyEl = document.getElementById('prod-stat-ready');

					if (totalEl) totalEl.innerText = total;
					if (inWorkEl) inWorkEl.innerText = preparation;
					if (qualityEl) qualityEl.innerText = client;
					if (readyEl) readyEl.innerText = success;

					console.log('Статистика обновлена:', {total, preparation, client, success});
				}).catch(function(error) {
					console.error('Ошибка при загрузке статистики:', error);
				});
			})();
			<?php endif; ?>
			// ========== КОНЕЦ ЗАГРУЗКИ СТАТИСТИКИ ==========

			BX.CRM.Kanban.Restriction.init({
				isUniversalActivityScenarioEnabled: 'true',
				isLastActivityEnabled: <?= ($arResult['IS_LAST_ACTIVITY_ENABLED'] ?? false) ? 'true' : 'false' ?>,
			});

			<?php if (isset($arResult['RESTRICTED_VALUE_CLICK_CALLBACK'])):?>
				BX.addCustomEvent(window, 'onCrmRestrictedValueClick', function() {
					<?=$arResult['RESTRICTED_VALUE_CLICK_CALLBACK'];?>
				});
			<?php endif;?>

			BX.Crm.PartialEditorDialog.messages =
			{
				entityHasInaccessibleFields: "<?= CUtil::JSEscape(Loc::getMessage('CRM_KANBAN_HAS_INACCESSIBLE_FIELDS')) ?>",
			};

			BX.Currency.setCurrencyFormat(
				"<?= $arParams['CURRENCY']?>",
				<?= CUtil::PhpToJSObject(\CCurrencyLang::GetFormatDescription($arParams['CURRENCY']), false, true)?>
			);

			BX.Crm.PartialEditorDialog.entityEditorUrls = {
				<?= CCrmOwnerType::DealName ?>: "<?= '/bitrix/components/bitrix/crm.deal.details/ajax.php?' . bitrix_sessid_get() ?>",
				<?= CCrmOwnerType::LeadName ?>: "<?= '/bitrix/components/bitrix/crm.lead.details/ajax.php?' . bitrix_sessid_get() ?>",
			};

			BX.Crm.EntityEditorUser.messages = {
				change: "<?= CUtil::JSEscape(Loc::getMessage('CRM_KANBAN_ED_CHANGE_USER')) ?>",
			};

			BX.UI.EntityEditorBoolean.messages = {
				yes: "<?= CUtil::JSEscape(Loc::getMessage('MAIN_YES')) ?>",
				no: "<?= CUtil::JSEscape(Loc::getMessage('MAIN_NO')) ?>",
			};

			BX.Crm.EntityEditorSection.messages = {
				change: "<?= CUtil::JSEscape(Loc::getMessage('CRM_KANBAN_ED_CHANGE')) ?>",
				cancel: "<?= CUtil::JSEscape(Loc::getMessage('CRM_KANBAN_ED_CANCEL')) ?>",
			};

			BX.CRM.Kanban.Item.messages = {
				company: "<?= CUtil::JSEscape(Loc::getMessage('CRM_KANBAN_COMPANY')) ?>",
				contact: "<?= CUtil::JSEscape(Loc::getMessage('CRM_KANBAN_CONTACT')) ?>",
				noname: "<?= CUtil::JSEscape(Loc::getMessage('FORMATNAME_NONAME')) ?>",
			};

			Kanban = new BX.CRM.Kanban.Grid(
				{
					renderTo: BX("crm_kanban"),
					itemType: "BX.CRM.Kanban.Item",
					columnType: "BX.CRM.Kanban.Column",
					dropZoneType: "BX.CRM.Kanban.DropZone",
					columnsRevert: <?= $arResult['CONFIG_BY_VIEW_MODE']['columnsRevert'] ?>,
					canAddColumn: <?= $arResult['CONFIG_BY_VIEW_MODE']['canAddColumn'] ?>,
					canEditColumn: <?= $arResult['CONFIG_BY_VIEW_MODE']['canEditColumn'] ?>,
					canRemoveColumn: <?= $arResult['CONFIG_BY_VIEW_MODE']['canRemoveColumn'] ?>,
					canSortColumn: <?= $arResult['CONFIG_BY_VIEW_MODE']['canSortColumn'] ?>,
					canSortItem: true,
					canChangeItemStage: <?= $arResult['CONFIG_BY_VIEW_MODE']['canChangeItemStage'] ?>,
					bgColor: 'transparent',
					columns: <?= CUtil::PhpToJSObject(array_values($data['columns']), false, false, true)?>,
					items: <?= CUtil::PhpToJSObject($data['items'], false, false, true)?>,
					dropZones: <?= CUtil::PhpToJSObject(array_values($data['dropzones']), false, false, true)?>,
					emptyStubItems: <?= CUtil::PhpToJSObject($arResult['STUB'] ?? null)?>,
					stageAnalyticsLabels: <?= JSON::encode($arResult['STAGE_ANALYTICS']) ?>,
					data:
						{
							itemsConfig: <?= Json::encode($data['config'] ?? []) ?>,
							contactCenterShow: <?= $arParams['HIDE_CC'] ? 'false' : 'true' ?>,
							restDemoBlockShow: <?= $arParams['HIDE_REST'] ? 'false' : 'true' ?>,
							reckonActivitylessItems: <?= CCrmUserCounterSettings::getValue(CCrmUserCounterSettings::ReckonActivitylessItems, true) ? 'true' : 'false';?>,
							ajaxHandlerPath,
							entityType: "<?= CUtil::JSEscape($arParams['ENTITY_TYPE_CHR'])?>",
							entityTypeInt: "<?= $entityTypeId ?>",
							typeInfo: <?= CUtil::PhpToJSObject($arParams['ENTITY_TYPE_INFO'])?>,
							viewMode: "<?= CUtil::JSEscape($arParams['VIEW_MODE'])?>",
							useItemPlanner: <?= ($arResult['USE_ITEM_PLANNER'] ? 'true' : 'false') ?>,
							skipColumnCountCheck: <?= ($arResult['SKIP_COLUMN_COUNT_CHECK'] ? 'true' : 'false') ?>,
							isDynamicEntity: <?= ($arParams['IS_DYNAMIC_ENTITY'] ? 'true' : 'false') ?>,
							entityPath: "<?= CUtil::JSEscape($arParams['ENTITY_PATH'])?>",
							editorConfigId: "<?= CUtil::JSEscape($arParams['EDITOR_CONFIG_ID'])?>",
							quickEditorPath: {
								lead: "/bitrix/components/bitrix/crm.lead.details/ajax.php?<?= bitrix_sessid_get() ?>",
								deal: "/bitrix/components/bitrix/crm.deal.details/ajax.php?<?= bitrix_sessid_get() ?>",
							},
							headersSections: <?= CUtil::PhpToJSObject($arResult['HEADERS_SECTIONS'] ?? [])?>,
							defaultHeaderSectionId: "<?= CUtil::JSEscape($arResult['DEFAULT_HEADER_SECTION_ID'] ?? '') ?>",
							params: <?= Json::encode($arParams['EXTRA']) ?>,
							gridId: "<?=CUtil::JSEscape($gridId)?>",
							converterId: "<?=CUtil::JSEscape($gridId)?>",
							showActivity: <?= $showActivity ?>,
							currency: "<?= $arParams['CURRENCY']?>",
							lastId: <?= (int)$data['last_id']?>,
							isLockedEntity: <?= $arResult['IS_LOCKED_ENTITY'] ? 'true' : 'false' ?>,
							lockedEntitySliderCode: 'limit_v2_crm_automated_solution_marketplace',
							rights: {
								canAddColumn: <?= !$arResult['IS_LOCKED_ENTITY'] && $arResult['ACCESS_CONFIG_PERMS'] ? 'true' : 'false' ?>,
								canEditColumn: <?= !$arResult['IS_LOCKED_ENTITY'] && $arResult['ACCESS_CONFIG_PERMS'] ? 'true' : 'false'?>,
								canRemoveColumn: <?= !$arResult['IS_LOCKED_ENTITY'] && $arResult['ACCESS_CONFIG_PERMS'] ? 'true' : 'false'?>,
								canSortColumn: <?= !$arResult['IS_LOCKED_ENTITY'] && $arResult['ACCESS_CONFIG_PERMS'] ? 'true' : 'false'?>,
								canImport: <?= !$arResult['IS_LOCKED_ENTITY'] && isset($arResult['ACCESS_IMPORT']) && $arResult['ACCESS_IMPORT'] ? 'true' : 'false'?>,
								canSortItem: <?= $arResult['IS_LOCKED_ENTITY'] ? 'true' : 'false' ?>,
								canUseVisit: <?= !$arResult['IS_LOCKED_ENTITY'] && Visit::isAvailable() ? 'true' : 'false' ?>,
							},
							visitParams: <?= CUtil::PhpToJSObject(Visit::getPopupParameters(), false, false, true)?>,
							admins: <?= CUtil::PhpToJSObject(array_values($arResult['ADMINS']))?>,
							userId: <?= $arParams['USER_ID'] ?>,
							currentUser: <?=Json::encode($arParams['LAYOUT_CURRENT_USER'])?>,
							pingSettings: <?=Json::encode($arParams['PING_SETTINGS'])?>,
							customFields: <?= CUtil::phpToJSObject(array_keys($arResult['MORE_FIELDS'])) ?>,
							customEditFields: <?= CUtil::phpToJSObject(array_keys($arResult['MORE_EDIT_FIELDS'])) ?>,
							restrictedFields: <?= CUtil::phpToJSObject($arResult['RESTRICTED_FIELDS']) ?>,
							userSelectorId: "kanban_multi_actions",
							linksPath: {
								marketplace: {
									url: "<?= CUtil::jsEscape(Router::getCategoryPath('migration', ['from' => 'kanban'])) ?>",
								},
								importexcel: {
									url: "<?= CUtil::jsEscape($arParams['PATH_TO_IMPORT']) ?>",
								},
								dealCategory: {
									url: "<?= CUtil::jsEscape($arParams['PATH_TO_DEAL_KANBANCATEGORY']) ?>",
								},
								contact_center: {
									url: "<?= $contactCenterUrl->addParams(['from' => 'kanban']) ?>",
								},
								rest_demo: {
									url: "<?= $arParams['REST_DEMO_URL'] ?>",
									params: {
										width: 940,
									},
								},
							},
							categories: <?= CUtil::phpToJSObject(array_values($arResult['CATEGORIES'])) ?>,
							pullTag: "<?= CUtil::JSEscape(PullManager::getInstance()->subscribeOnKanbanUpdate(
								$arParams['ENTITY_TYPE_CHR'],
								$arParams['EXTRA']
							)) ?>",
							additionalPullTags: <?= Json::encode(PullManager::getInstance()->getAdditionalPullTags(
								$arParams['ENTITY_TYPE_CHR'],
								$arParams['EXTRA']
							)) ?>,
							moduleId: "<?= CUtil::JSEscape(PullManager::MODULE_ID) ?>",
							tariffRestrictions: {
								addItemNotPermittedByTariff: <?= !($arParams['EXTRA']['ADD_ITEM_PERMITTED_BY_TARIFF'] ?? true) ? 'true' : 'false' ?>,
							},
							showErrorCounterByActivityResponsible: <?= $arResult['SHOW_ERROR_COUNTER_BY_ACTIVITY_RESPONSIBLE'] ? 'true' : 'false' ?>,
							isActivityLimitIsExceeded: <?= $isActivityLimitIsExceeded ? 'true' : 'false' ?>,
							analytics: {
								c_section: '<?= CUtil::JSEscape($section) ?>',
								c_sub_section: '<?= CUtil::JSEscape($subSection) ?>',
							},
							performance: <?= CUtil::phpToJSObject($arResult['PERFORMANCE']) ?>,
						}
				}
			);

			BX.addCustomEvent("Crm.Kanban.Grid:onSpecialItemDraw", BX.delegate(BX.Crm.KanbanComponent.onSpecialItemDraw, this));

			Kanban.draw();

			<?php if ($arParams['ENTITY_TYPE_CHR'] === 'LEAD' || $arParams['ENTITY_TYPE_CHR'] === 'INVOICE'): ?>
			BX.addCustomEvent("Crm.Kanban.Grid:onItemMovedFinal", BX.delegate(BX.Crm.KanbanComponent.columnPopup, this));
			<?php endif; ?>

			<?php if ($arParams['ENTITY_TYPE_CHR'] === 'INVOICE'): ?>
			BX.addCustomEvent("Crm.Kanban.Grid:onBeforeItemCapturedStart", BX.delegate(BX.Crm.KanbanComponent.dropPopup, this));
			<?php endif; ?>

			<?php if ($arParams['ENTITY_TYPE_CHR'] === 'LEAD'): ?>
			BX.addCustomEvent("onPopupClose", BX.proxy(BX.Crm.KanbanComponent.onPopupClose, this));
			<?php endif; ?>

			BX.message(
				{
					CRM_KANBAN_POPUP_PARAMS_SAVE: "<?= CUtil::JSEscape(Loc::getMessage('CRM_KANBAN_POPUP_PARAMS_SAVE')) ?>",
					CRM_KANBAN_POPUP_PARAMS_CANCEL: "<?= CUtil::JSEscape(Loc::getMessage('CRM_KANBAN_POPUP_PARAMS_CANCEL')) ?>",
					CRM_KANBAN_DELETE_SUCCESS_MULTIPLE: "<?= GetMessageJS('CRM_KANBAN_DELETE_SUCCESS_MULTIPLE_MSGVER_1') ?>",
					CRM_KANBAN_DELETE_SUCCESS_MULTIPLE_WITH_ERRORS: "<?= GetMessageJS('CRM_KANBAN_DELETE_SUCCESS_MULTIPLE_WITH_ERRORS') ?>",
					CRM_KANBAN_DELETE_SUCCESS: "<?= GetMessageJS('CRM_KANBAN_DELETE_SUCCESS_MSGVER_1') ?>",
					CRM_KANBAN_DELETE_CANCEL: "<?= GetMessageJS('CRM_KANBAN_DELETE_CANCEL') ?>",
					CRM_KANBAN_DELETE_RESTORE_SUCCESS: "<?= GetMessageJS('CRM_KANBAN_DELETE_RESTORE_SUCCESS_MSGVER_1') ?>",
					CRM_TYPE_ITEM_PARTIAL_EDITOR_TITLE: "<?= GetMessageJS('CRM_TYPE_ITEM_PARTIAL_EDITOR_TITLE')?>",
					CRM_KANBAN_OPEN_ITEM: "<?= GetMessageJS('CRM_KANBAN_OPEN_ITEM')?>",
				}
			);

			new BX.Crm.Kanban.PullManager(Kanban);

			const sortSettings = BX.CRM.Kanban.Sort.Settings.createFromJson(
				'<?= Json::encode($arResult['SORT_SETTINGS']) ?>',
			);
			BX.CRM.Kanban.Sort.SettingsController.init(Kanban, sortSettings);

			<?php if ($isMergeEnabled): ?>
				BX.Crm.BatchMergeManager.create(
					"<?=CUtil::JSEscape($gridId)?>",
					{
						kanban: Kanban,
						entityTypeId: "<?= $entityTypeId ?>",
						mergerUrl: "<?=CUtil::JSEscape($arParams['PATH_TO_MERGE'])?>"
					}
				);
			<?php endif; ?>

			<?php
				$factory = Container::getInstance()->getFactory($entityTypeId);
				if ($factory)
				{
					$settingsButtonExtenderParams = new SettingsButtonExtenderParams(
						$factory,
					);
					$settingsButtonExtenderParams
						->setCategoryId(isset($arParams['EXTRA']['CATEGORY_ID']) ? (int)$arParams['EXTRA']['CATEGORY_ID'] : null)
						->setTargetItemId('crm_kanban_cc_delimiter')
						->setGetRootMenuJsCallback('Kanban.getSettingsButtonMenu()')
						->setGetKanbanSortSettingsControllerJsCallback('BX.CRM.Kanban.Sort.SettingsController.Instance')
						->setGetKanbanRestrictionJsCallback('BX.CRM.Kanban.Restriction.Instance')
					;

					echo $settingsButtonExtenderParams->buildJsInitCode();
				}
			?>
		}
	);
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . $this->getFolder() . '/popups.php' ?>

<?php if (isset($arParams['ENTITY_TYPE_CHR']) && $arParams['ENTITY_TYPE_CHR'] === 'LEAD'):
	print (Tour\NumberOfClients::getInstance())->build();

	Loc::loadMessages($_SERVER['DOCUMENT_ROOT'].'/bitrix/components/bitrix/crm.lead.list/templates/.default/template.php');

	Extension::load(['crm.conversion', 'crm.integration.analytics']);

	/** @var LeadConversionConfig $conversionConfig */
	$conversionConfig = $arResult['CONVERSION_CONFIG'];
	?>
	<script>
		BX.ready(
			function()
			{
				BX.CrmEntityType.captions =
				{
					<?= CCrmOwnerType::LeadName?>: "<?= CCrmOwnerType::GetDescription(CCrmOwnerType::Lead)?>",
					<?= CCrmOwnerType::ContactName?>: "<?= CCrmOwnerType::GetDescription(CCrmOwnerType::Contact)?>",
					<?= CCrmOwnerType::CompanyName?>: "<?= CCrmOwnerType::GetDescription(CCrmOwnerType::Company)?>",
					<?= CCrmOwnerType::DealName?>: "<?= CCrmOwnerType::GetDescription(CCrmOwnerType::Deal)?>",
					<?= CCrmOwnerType::InvoiceName?>: "<?= CCrmOwnerType::GetDescription(CCrmOwnerType::Invoice)?>",
					<?= CCrmOwnerType::OrderName?>: "<?= CCrmOwnerType::GetDescription(CCrmOwnerType::Order)?>",
					<?= CCrmOwnerType::QuoteName?>: "<?= CCrmOwnerType::GetDescription(CCrmOwnerType::Quote)?>"
				};

				BX.Crm.Conversion.Manager.Instance.initializeConverter(
					BX.CrmEntityType.enumeration.lead,
					{
						configItems: <?= CUtil::PhpToJSObject($conversionConfig->toJson()) ?>,
						scheme: <?= CUtil::PhpToJSObject($conversionConfig->getScheme()->toJson(true)) ?>,
						params: {
							id: '<?= CUtil::JSEscape($gridId) ?>',
							serviceUrl: "<?='/bitrix/components/bitrix/crm.lead.show/ajax.php?action=convert&'.bitrix_sessid_get()?>",
							messages: {
								accessDenied: "<?=GetMessageJS("CRM_LEAD_CONV_ACCESS_DENIED")?>",
								generalError: "<?=GetMessageJS("CRM_LEAD_CONV_GENERAL_ERROR")?>",
								dialogTitle: "<?=GetMessageJS("CRM_LEAD_CONV_DIALOG_TITLE")?>",
								syncEditorLegend: "<?=GetMessageJS("CRM_LEAD_CONV_DIALOG_SYNC_LEGEND")?>",
								syncEditorFieldListTitle: "<?=GetMessageJS("CRM_LEAD_CONV_DIALOG_SYNC_FILED_LIST_TITLE")?>",
								syncEditorEntityListTitle: "<?=GetMessageJS("CRM_LEAD_CONV_DIALOG_SYNC_ENTITY_LIST_TITLE")?>",
								continueButton: "<?=GetMessageJS("CRM_LEAD_CONV_DIALOG_CONTINUE_BTN")?>",
								cancelButton: "<?=GetMessageJS("CRM_LEAD_CONV_DIALOG_CANCEL_BTN")?>",

								selectButton: "<?=GetMessageJS("CRM_LEAD_CONV_ENTITY_SEL_BTN")?>",
								openEntitySelector: "<?=GetMessageJS("CRM_LEAD_CONV_OPEN_ENTITY_SEL")?>",
								entitySelectorTitle: "<?=GetMessageJS("CRM_LEAD_CONV_ENTITY_SEL_TITLE")?>",
								contact: "<?=GetMessageJS("CRM_LEAD_CONV_ENTITY_SEL_CONTACT")?>",
								company: "<?=GetMessageJS("CRM_LEAD_CONV_ENTITY_SEL_COMPANY")?>",
								noresult: "<?=GetMessageJS("CRM_LEAD_CONV_ENTITY_SEL_SEARCH_NO_RESULT")?>",
								search : "<?=GetMessageJS("CRM_LEAD_CONV_ENTITY_SEL_SEARCH")?>",
								last : "<?=GetMessageJS("CRM_LEAD_CONV_ENTITY_SEL_LAST")?>",
							},
							analytics: {
								c_sub_section: '<?=
									$arParams['VIEW_MODE'] === ViewMode::MODE_ACTIVITIES
										? Dictionary::SUB_SECTION_ACTIVITIES
										: Dictionary::SUB_SECTION_KANBAN
								?>',
							},
						}
					},
				);
			}
		);
	</script>
<?php elseif (isset($arParams['ENTITY_TYPE_CHR']) && $arParams['ENTITY_TYPE_CHR'] === 'DEAL'):
	print (Tour\NumberOfClients::getInstance())->build();

	NotificationsManager::showSignUpFormOnCrmShopCreated();
endif;

if (!empty($arResult['RESTRICTED_FIELDS_ENGINE']))
{
	Extension::load(['crm.restriction.filter-fields']);

	echo $arResult['RESTRICTED_FIELDS_ENGINE'];
}
?>

<!-- ========== КРАСНАЯ ПОДСВЕТКА (ЧЕРЕЗ API) ========== -->
<?php if ($entityTypeId == 1038): ?>
<script>
(function() {
    // ID стадии "Нарушение сроков"
    const VIOLATION_STAGE_ID = 'DT1038_8:UC_B00ZPF';
    
    function highlightViolationCards() {
        console.log('Красная подсветка через API...');
        
        // Получаем все задания со стадией "Нарушение сроков"
        BX.ajax.runAction('crm.api.item.list', {
            data: {
                entityTypeId: 1038,
                categoryId: 8,
                select: ['id', 'stageId'],
            }
        }).then(function(response) {
            if (!response.data || !response.data.items) {
                console.error('Ошибка получения данных');
                return;
            }
            
            // Находим ID заданий с нужной стадией
            var violationIds = [];
            response.data.items.forEach(function(item) {
                if (item.stageId === VIOLATION_STAGE_ID) {
                    violationIds.push(item.id);
                }
            });
            
            console.log('Заданий со стадией "Нарушение сроков":', violationIds.length);
            if (violationIds.length > 0) {
                console.log('ID заданий:', violationIds);
            }
            
            // Ищем карточки на странице и красим их
            var cards = document.querySelectorAll('.crm-kanban-item');
            console.log('Найдено карточек на странице:', cards.length);
            
            cards.forEach(function(card) {
                // Ищем ссылку внутри карточки
                var link = card.querySelector('.crm-kanban-item-title');
                if (link && link.href) {
                    // Ищем ID в формате /details/9/ (с буквой s)
                    var match = link.href.match(/\/details\/(\d+)\//);
                    if (!match) {
                        // Если не нашли, пробуем другой формат - /detail/9/
                        match = link.href.match(/\/detail\/(\d+)\//);
                    }
                    if (match && violationIds.includes(parseInt(match[1]))) {
                        console.log('Найдена карточка с нарушением сроков, ID:', match[1]);
                        card.style.setProperty('background-color', '#ffcccc', 'important');
                        card.style.setProperty('border-left', '4px solid #ff0000', 'important');
                        card.style.setProperty('border-radius', '4px', 'important');
                        var title = card.querySelector('.crm-kanban-item-title');
                        if (title && title.innerHTML.indexOf('⚠️') === -1) {
                            title.innerHTML = '⚠️ ' + title.innerHTML;
                        }
                    }
                }
            });
        }).catch(function(error) {
            console.error('Ошибка API:', error);
        });
    }
    
    // Запускаем несколько раз
    setTimeout(highlightViolationCards, 1000);
    setTimeout(highlightViolationCards, 3000);
    setTimeout(highlightViolationCards, 5000);
    setTimeout(highlightViolationCards, 8000);
    
    if (typeof BX !== 'undefined') {
        BX.addCustomEvent('Crm.Kanban.Grid:onAfterDraw', function() {
            setTimeout(highlightViolationCards, 200);
        });
        BX.addCustomEvent('Crm.Kanban.Grid:onItemMoved', function() {
            setTimeout(highlightViolationCards, 200);
        });
    }
    
    var observer = new MutationObserver(function() {
        highlightViolationCards();
    });
    
    var container = document.getElementById('crm_kanban');
    if (container) {
        observer.observe(container, { childList: true, subtree: true });
    }
})();
</script>
<?php endif; ?>
<!-- ========== КОНЕЦ КРАСНОЙ ПОДСВЕТКИ ========== -->

<!-- ========== ЖЁЛТАЯ ПОДСВЕТКА ПО ДЕДЛАЙНУ ========== -->
<?php if ($entityTypeId == 1038): ?>
<script>
(function() {
    function highlightByDeadline() {
        var cards = document.querySelectorAll('.crm-kanban-item');
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        
        cards.forEach(function(card) {
            // Пропускаем карточки, которые уже красные (есть иконка ⚠️)
            if (card.innerText.indexOf('⚠️') !== -1) return;
            
            // Ищем дату дедлайна
            var dateElements = card.querySelectorAll('.crm-kanban-item-fields-item-value');
            var deadlineDate = null;
            
            for (var i = 0; i < dateElements.length; i++) {
                var text = dateElements[i].innerText.trim();
                if (text.match(/\d{2}\.\d{2}\.\d{4}/)) {
                    var parts = text.split('.');
                    if (parts.length === 3) {
                        deadlineDate = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
                        break;
                    }
                }
            }
            
            if (!deadlineDate) return;
            
            var daysLeft = Math.ceil((deadlineDate - today) / (1000 * 60 * 60 * 24));
            
            // Жёлтая подсветка (0–2 дня)
            if (daysLeft >= 0 && daysLeft <= 2) {
                card.style.setProperty('background-color', '#fff3cd', 'important');
                card.style.setProperty('border-left', '4px solid #ffc107', 'important');
                var title = card.querySelector('.crm-kanban-item-title');
                if (title && title.innerHTML.indexOf('⏰') === -1 && title.innerHTML.indexOf('⚠️') === -1) {
                    title.innerHTML = '⏰ ' + title.innerHTML;
                }
            }
        });
    }
    
    setTimeout(highlightByDeadline, 1000);
    setTimeout(highlightByDeadline, 2000);
    setTimeout(highlightByDeadline, 3000);
    setTimeout(highlightByDeadline, 5000);
    
    if (typeof BX !== 'undefined') {
        BX.addCustomEvent('Crm.Kanban.Grid:onAfterDraw', function() {
            setTimeout(highlightByDeadline, 200);
        });
        BX.addCustomEvent('Crm.Kanban.Grid:onItemMoved', function() {
            setTimeout(highlightByDeadline, 200);
        });
    }
})();
</script>
<?php endif; ?>
<!-- ========== КОНЕЦ ЖЁЛТОЙ ПОДСВЕТКИ ========== -->

<?php
// ========== КОНЕЦ ФАЙЛА ==========
?>