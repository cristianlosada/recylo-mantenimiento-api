<?php

namespace App;

enum UnitOfMeasure: string
{
    // Unidades discretas
    case UNIDAD = 'unidad';
    case PAR = 'par';
    case JUEGO = 'juego';
    case CAJA = 'caja';
    case PAQUETE = 'paquete';
    case ROLLO = 'rollo';
    case BOBINA = 'bobina';
    
    // Peso
    case KG = 'kg';
    case GR = 'gr';
    case TON = 'ton';
    case LB = 'lb';
    case OZ = 'oz';
    
    // Volumen
    case LT = 'lt';
    case ML = 'ml';
    case GAL = 'gal';
    case M3 = 'm3';
    case BARRIL = 'barril';
    
    // Longitud
    case MT = 'mt';
    case CM = 'cm';
    case MM = 'mm';
    case PULG = 'pulg';
    case PIE = 'pie';
    
    // Área
    case M2 = 'm2';
    case CM2 = 'cm2';
    case PIE2 = 'pie2';
    
    // Eléctricos
    case KWH = 'kwh';
    case AMP = 'amp';
    case VOLT = 'volt';

    /**
     * Obtener etiqueta descriptiva
     */
    public function label(): string
    {
        return match($this) {
            // Discretas
            self::UNIDAD => 'Unidad (pza)',
            self::PAR => 'Par',
            self::JUEGO => 'Juego (set)',
            self::CAJA => 'Caja',
            self::PAQUETE => 'Paquete',
            self::ROLLO => 'Rollo',
            self::BOBINA => 'Bobina',
            
            // Peso
            self::KG => 'Kilogramo (kg)',
            self::GR => 'Gramo (g)',
            self::TON => 'Tonelada (ton)',
            self::LB => 'Libra (lb)',
            self::OZ => 'Onza (oz)',
            
            // Volumen
            self::LT => 'Litro (L)',
            self::ML => 'Mililitro (ml)',
            self::GAL => 'Galón (gal)',
            self::M3 => 'Metro cúbico (m³)',
            self::BARRIL => 'Barril',
            
            // Longitud
            self::MT => 'Metro (m)',
            self::CM => 'Centímetro (cm)',
            self::MM => 'Milímetro (mm)',
            self::PULG => 'Pulgada (in)',
            self::PIE => 'Pie (ft)',
            
            // Área
            self::M2 => 'Metro cuadrado (m²)',
            self::CM2 => 'Centímetro cuadrado (cm²)',
            self::PIE2 => 'Pie cuadrado (ft²)',
            
            // Eléctricos
            self::KWH => 'Kilovatio-hora (kWh)',
            self::AMP => 'Amperio (A)',
            self::VOLT => 'Voltio (V)',
        };
    }

    /**
     * Obtener tipo de unidad
     */
    public function type(): string
    {
        return match($this) {
            self::UNIDAD, self::PAR, self::JUEGO, self::CAJA, 
            self::PAQUETE, self::ROLLO, self::BOBINA => 'discrete',
            
            self::KG, self::GR, self::TON, self::LB, self::OZ => 'weight',
            
            self::LT, self::ML, self::GAL, self::M3, self::BARRIL => 'volume',
            
            self::MT, self::CM, self::MM, self::PULG, self::PIE => 'length',
            
            self::M2, self::CM2, self::PIE2 => 'area',
            
            self::KWH, self::AMP, self::VOLT => 'electric',
        };
    }

    /**
     * Obtener todas las unidades con sus detalles
     */
    public static function all(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'type' => $case->type(),
        ], self::cases());
    }

    /**
     * Obtener unidades por tipo
     */
    public static function byType(string $type): array
    {
        return array_filter(
            self::all(),
            fn($unit) => $unit['type'] === $type
        );
    }
}

