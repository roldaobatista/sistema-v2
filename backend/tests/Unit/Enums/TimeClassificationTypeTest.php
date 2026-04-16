<?php

namespace Tests\Unit\Enums;

use App\Enums\TimeClassificationType;

it('has all expected classification cases', function () {
    $cases = TimeClassificationType::cases();

    expect($cases)->toHaveCount(18);

    $values = array_map(fn ($c) => $c->value, $cases);
    expect($values)->toContain(
        'jornada_normal',
        'hora_extra',
        'intervalo',
        'deslocamento_cliente',
        'deslocamento_entre',
        'espera_local',
        'execucao_servico',
        'almoco_viagem',
        'pernoite',
        'sobreaviso',
        'plantao',
        'tempo_improdutivo',
        'ausencia',
        'atestado',
        'folga',
        'compensacao',
        'adicional_noturno',
        'dsr',
    );
});

it('returns human-readable labels for all cases', function () {
    foreach (TimeClassificationType::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

it('correctly identifies work time classifications', function () {
    expect(TimeClassificationType::JORNADA_NORMAL->isWorkTime())->toBeTrue();
    expect(TimeClassificationType::HORA_EXTRA->isWorkTime())->toBeTrue();
    expect(TimeClassificationType::EXECUCAO_SERVICO->isWorkTime())->toBeTrue();
    expect(TimeClassificationType::ADICIONAL_NOTURNO->isWorkTime())->toBeTrue();

    expect(TimeClassificationType::INTERVALO->isWorkTime())->toBeFalse();
    expect(TimeClassificationType::DESLOCAMENTO_CLIENTE->isWorkTime())->toBeFalse();
    expect(TimeClassificationType::FOLGA->isWorkTime())->toBeFalse();
    expect(TimeClassificationType::PERNOITE->isWorkTime())->toBeFalse();
});

it('correctly identifies paid time classifications', function () {
    expect(TimeClassificationType::JORNADA_NORMAL->isPaidTime())->toBeTrue();
    expect(TimeClassificationType::SOBREAVISO->isPaidTime())->toBeTrue();
    expect(TimeClassificationType::PLANTAO->isPaidTime())->toBeTrue();
    expect(TimeClassificationType::DSR->isPaidTime())->toBeTrue();

    expect(TimeClassificationType::INTERVALO->isPaidTime())->toBeFalse();
    expect(TimeClassificationType::FOLGA->isPaidTime())->toBeFalse();
    expect(TimeClassificationType::AUSENCIA->isPaidTime())->toBeFalse();
});

it('correctly identifies absence classifications', function () {
    expect(TimeClassificationType::AUSENCIA->isAbsence())->toBeTrue();
    expect(TimeClassificationType::ATESTADO->isAbsence())->toBeTrue();
    expect(TimeClassificationType::FOLGA->isAbsence())->toBeTrue();
    expect(TimeClassificationType::COMPENSACAO->isAbsence())->toBeTrue();

    expect(TimeClassificationType::JORNADA_NORMAL->isAbsence())->toBeFalse();
    expect(TimeClassificationType::HORA_EXTRA->isAbsence())->toBeFalse();
});

it('can be instantiated from string value', function () {
    $type = TimeClassificationType::from('jornada_normal');

    expect($type)->toBe(TimeClassificationType::JORNADA_NORMAL);
    expect($type->label())->toBe('Jornada Normal');
});

it('throws exception for invalid value', function () {
    TimeClassificationType::from('invalid_value');
})->throws(\ValueError::class);

it('tryFrom returns null for invalid value', function () {
    expect(TimeClassificationType::tryFrom('invalid_value'))->toBeNull();
});
