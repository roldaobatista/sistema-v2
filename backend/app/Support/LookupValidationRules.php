<?php

namespace App\Support;

use App\Models\Lookups\AccountReceivableCategory;
use App\Models\Lookups\AutomationReportFormat;
use App\Models\Lookups\AutomationReportFrequency;
use App\Models\Lookups\AutomationReportType;
use App\Models\Lookups\BankAccountType;
use App\Models\Lookups\CalibrationType;
use App\Models\Lookups\CancellationReason;
use App\Models\Lookups\ContractType;
use App\Models\Lookups\CustomerCompanySize;
use App\Models\Lookups\CustomerRating;
use App\Models\Lookups\CustomerSegment;
use App\Models\Lookups\DocumentType;
use App\Models\Lookups\EquipmentBrand;
use App\Models\Lookups\EquipmentCategory;
use App\Models\Lookups\EquipmentType;
use App\Models\Lookups\FleetFuelType;
use App\Models\Lookups\FleetVehicleStatus;
use App\Models\Lookups\FleetVehicleType;
use App\Models\Lookups\FollowUpChannel;
use App\Models\Lookups\FollowUpStatus;
use App\Models\Lookups\FuelingFuelType;
use App\Models\Lookups\InmetroSealStatus;
use App\Models\Lookups\InmetroSealType;
use App\Models\Lookups\LeadSource;
use App\Models\Lookups\MaintenanceType;
use App\Models\Lookups\MeasurementUnit;
use App\Models\Lookups\OnboardingTemplateType;
use App\Models\Lookups\PaymentTerm;
use App\Models\Lookups\PriceTableAdjustmentType;
use App\Models\Lookups\QuoteSource;
use App\Models\Lookups\ServiceType;
use App\Models\Lookups\SupplierContractPaymentFrequency;
use App\Models\Lookups\TvCameraType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LookupValidationRules
{
    public const TYPE_MAP = [
        'equipment-categories' => EquipmentCategory::class,
        'equipment-types' => EquipmentType::class,
        'equipment-brands' => EquipmentBrand::class,
        'customer-segments' => CustomerSegment::class,
        'customer-company-sizes' => CustomerCompanySize::class,
        'customer-ratings' => CustomerRating::class,
        'lead-sources' => LeadSource::class,
        'contract-types' => ContractType::class,
        'measurement-units' => MeasurementUnit::class,
        'calibration-types' => CalibrationType::class,
        'maintenance-types' => MaintenanceType::class,
        'document-types' => DocumentType::class,
        'account-receivable-categories' => AccountReceivableCategory::class,
        'cancellation-reasons' => CancellationReason::class,
        'service-types' => ServiceType::class,
        'payment-terms' => PaymentTerm::class,
        'quote-sources' => QuoteSource::class,
        'bank-account-types' => BankAccountType::class,
        'fleet-vehicle-types' => FleetVehicleType::class,
        'fleet-fuel-types' => FleetFuelType::class,
        'fleet-vehicle-statuses' => FleetVehicleStatus::class,
        'fueling-fuel-types' => FuelingFuelType::class,
        'inmetro-seal-types' => InmetroSealType::class,
        'inmetro-seal-statuses' => InmetroSealStatus::class,
        'tv-camera-types' => TvCameraType::class,
        'onboarding-template-types' => OnboardingTemplateType::class,
        'follow-up-channels' => FollowUpChannel::class,
        'follow-up-statuses' => FollowUpStatus::class,
        'price-table-adjustment-types' => PriceTableAdjustmentType::class,
        'automation-report-types' => AutomationReportType::class,
        'automation-report-frequencies' => AutomationReportFrequency::class,
        'automation-report-formats' => AutomationReportFormat::class,
        'supplier-contract-payment-frequencies' => SupplierContractPaymentFrequency::class,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $type, ?int $ignoreId = null, ?int $tenantId = null): array
    {
        $modelClass = self::TYPE_MAP[$type] ?? null;
        if (! $modelClass) {
            return [];
        }

        $table = (new $modelClass)->getTable();
        $tenantId = $tenantId ?? (app()->bound('current_tenant_id') ? app('current_tenant_id') : null);

        $slugUnique = Rule::unique($table, 'slug')->where('tenant_id', $tenantId);
        if ($ignoreId !== null) {
            $slugUnique->ignore($ignoreId);
        }

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($table, $tenantId, $ignoreId): void {
                    $slug = Str::slug($value);
                    if ($slug === '') {
                        return;
                    }
                    $query = DB::table($table)->where('tenant_id', $tenantId)->where('slug', $slug)->whereNull('deleted_at');
                    if ($ignoreId !== null) {
                        $query->where('id', '!=', $ignoreId);
                    }
                    if ($query->exists()) {
                        $fail(__('validation.unique', ['attribute' => 'nome']));
                    }
                },
            ],
            'slug' => ['nullable', 'string', 'max:255', $slugUnique],
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];

        if ($type === 'measurement-units') {
            $rules['abbreviation'] = 'nullable|string|max:20';
            $rules['unit_type'] = 'nullable|string|max:30';
        }

        if ($type === 'cancellation-reasons') {
            $rules['applies_to'] = 'nullable|array';
            $rules['applies_to.*'] = 'string|in:os,chamado,orcamento';
        }

        return $rules;
    }

    public static function resolveModel(string $type): ?string
    {
        return self::TYPE_MAP[$type] ?? null;
    }
}
