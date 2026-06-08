<?php

namespace App\Console\Commands;

use App\Services\MaintenancePlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckDueMaintenancePlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintenance:check-due-plans {--company= : Company ID to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and execute due maintenance plans (time-based, meter-based, and hybrid)';

    protected $maintenancePlanService;

    /**
     * Create a new command instance.
     */
    public function __construct(MaintenancePlanService $maintenancePlanService)
    {
        parent::__construct();
        $this->maintenancePlanService = $maintenancePlanService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $companyId = $this->option('company');
        
        $this->info('=================================================');
        $this->info('🔧 VERIFICACIÓN DE PLANES DE MANTENIMIENTO VENCIDOS');
        $this->info('=================================================');
        
        if ($companyId) {
            $this->info("📍 Empresa: ID {$companyId}");
        } else {
            $this->info("📍 Alcance: Todas las empresas");
        }
        
        $this->info("🕐 Fecha/Hora: " . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        try {
            // Ejecutar verificación de planes vencidos
            $result = $this->maintenancePlanService->checkDuePlans($companyId);

            $executedCount = $result['executed'];
            $errorsCount = count($result['errors']);
            $totalProcessed = $executedCount + $errorsCount;

            // Mostrar resultados
            $this->info("📊 RESULTADOS:");
            $this->info("   • Planes procesados: {$totalProcessed}");
            $this->info("   • Órdenes generadas: {$executedCount}");
            
            if ($errorsCount > 0) {
                $this->warn("   • Errores encontrados: {$errorsCount}");
                $this->newLine();
                
                $this->warn("⚠️  ERRORES DETALLADOS:");
                foreach ($result['errors'] as $error) {
                    $this->error("   ✗ Plan ID {$error['plan_id']}: {$error['error']}");
                }
            }

            $this->newLine();

            if ($executedCount > 0) {
                $this->info("✓ {$executedCount} órdenes de trabajo generadas exitosamente");
                
                // Log de éxito
                Log::info('Comando maintenance:check-due-plans ejecutado exitosamente', [
                    'company_id' => $companyId,
                    'executed' => $executedCount,
                    'errors' => $errorsCount,
                    'timestamp' => now()->toDateTimeString()
                ]);
                
                return Command::SUCCESS;
            } else {
                $this->info("ℹ️  No hay planes de mantenimiento que requieran ejecución en este momento");
                
                return Command::SUCCESS;
            }

        } catch (\Exception $e) {
            $this->error("✗ Error crítico al verificar planes de mantenimiento:");
            $this->error("   {$e->getMessage()}");
            $this->newLine();

            // Log de error crítico
            Log::error('Error crítico en comando maintenance:check-due-plans', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toDateTimeString()
            ]);

            return Command::FAILURE;
        }
    }
}
