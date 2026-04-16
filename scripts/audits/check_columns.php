
<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$models = ['AccountPlanAction', 'AgendaItemComment', 'AgendaItemHistory', 'AssetTagScan', 'CompetitorInstrumentRepair', 'CrmDealCompetitor', 'CrmSequenceStep', 'CrmTerritoryMember', 'CrmWebFormSubmission', 'EmailAttachment', 'InmetroHistory', 'InssBracket', 'IrrfBracket', 'ManagementReviewAction', 'MaterialRequestItem', 'MinimumWage', 'OperationalSnapshot', 'PartsKitItem', 'PermissionGroup', 'PriceTableItem', 'ProductKit', 'PurchaseQuotationItem', 'PurchaseQuoteItem', 'PurchaseQuoteSupplier', 'QualityAuditItem', 'ReturnedUsedItemDisposition', 'RmaItem', 'ServiceCatalogItem', 'ServiceChecklistItem', 'StockDisposalItem', 'StockTransferItem', 'TwoFactorAuth', 'UserFavorite', 'UserSession', 'VisitRouteStop', 'WarehouseStock', 'WebhookLog'];
foreach ($models as $m) {
    $class = "App\\Models\\$m";
    if (! class_exists($class)) {
        continue;
    }
    $instance = new $class;
    $table = $instance->getTable();
    $hasTenant = Schema::hasColumn($table, 'tenant_id');
    echo "$m: ".($hasTenant ? 'YES' : 'NO')."\n";
}
