from pathlib import Path
from datetime import datetime
import re

root = Path.cwd()
writer = root / "app/Http/Controllers/Api/V1/DriverLocationController.php"
reader = root / "app/Http/Controllers/Api/V1/DeliveryTrackingController.php"
migration_dir = root / "database/migrations"

for path in (writer, reader):
    if not path.exists():
        raise SystemExit(f"Missing: {path}")

if list(migration_dir.glob("*create_delivery_driver_locations_table.php")):
    migration = list(migration_dir.glob(
        "*create_delivery_driver_locations_table.php"
    ))[-1]
else:
    migration = migration_dir / (
        datetime.now().strftime("%Y_%m_%d_%H%M%S")
        + "_create_delivery_driver_locations_table.php"
    )

migration_content = """<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_driver_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique('delivery_id');
            $table->index(['driver_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_driver_locations');
    }
};
"""

writer_content = writer.read_text()
reader_content = reader.read_text()

if "delivery_driver_locations" in writer_content or \
   "delivery_driver_locations" in reader_content:
    raise SystemExit("Tracking changes appear to exist already; inspect first.")

writer_import = "use Illuminate\\Http\\JsonResponse;"
if writer_content.count(writer_import) != 1:
    raise SystemExit("Writer import anchor not found.")

writer_content = writer_content.replace(
    writer_import,
    "use Illuminate\\Support\\Facades\\DB;\n" + writer_import,
    1,
)

writer_content = writer_content.replace(
    "'latitude' => ['required', 'numeric', 'between:-90,90'],",
    "'delivery_id' => ['required', 'integer', 'exists:deliveries,id'],\n"
    "            'latitude' => ['required', 'numeric', 'between:-90,90'],",
    1,
)

start = writer_content.find("        $hasActiveDelivery =")
end = writer_content.find("        return response()->json([", start)

if start == -1 or end == -1:
    raise SystemExit("Could not locate writer assignment validation.")

writer_content = (
    writer_content[:start]
    + """        $assignment = DeliveryAssignment::query()
            ->where('delivery_id', $validated['delivery_id'])
            ->where('driver_id', $driver->id)
            ->where('status', 'accepted')
            ->whereHas('delivery', fn ($query) => $query->whereIn(
                'status',
                ['accepted', 'pickup', 'in_transit']
            ))
            ->first();

        if (! $assignment) {
"""
    + writer_content[end:]
)

writer_content = writer_content.replace(
    "        $driver->update([\n"
    "            'current_latitude' => $validated['latitude'],\n"
    "            'current_longitude' => $validated['longitude'],\n"
    "            'last_location_at' => now(),\n"
    "        ]);",
    """        DB::transaction(function () use ($driver, $validated) {
            $now = now();

            DB::table('delivery_driver_locations')->updateOrInsert(
                ['delivery_id' => $validated['delivery_id']],
                [
                    'driver_id' => $driver->id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'recorded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $driver->update([
                'current_latitude' => $validated['latitude'],
                'current_longitude' => $validated['longitude'],
                'last_location_at' => $now,
            ]);
        });""",
    1,
)

reader_content = reader_content.replace(
    "use App\\Models\\Driver;\n", "",
    1,
)

reader_content = reader_content.replace(
    "use Illuminate\\Http\\JsonResponse;",
    "use Illuminate\\Support\\Facades\\DB;\n"
    "use Illuminate\\Http\\JsonResponse;",
    1,
)

start = reader_content.find("        $assignment = DeliveryAssignment::query()", 
                            reader_content.find("$timeline ="))
end = reader_content.find("        return response()->json([", start)

if start == -1 or end == -1:
    raise SystemExit("Could not locate reader location section.")

reader_content = (
    reader_content[:start]
    + """        $driverLocation = null;

        if (in_array($delivery->status->value, [
            'accepted', 'pickup', 'in_transit',
        ], true)) {
            $location = DB::table('delivery_driver_locations')
                ->join(
                    'delivery_assignments',
                    'delivery_assignments.driver_id',
                    '=',
                    'delivery_driver_locations.driver_id'
                )
                ->where('delivery_driver_locations.delivery_id', $delivery->id)
                ->where('delivery_assignments.delivery_id', $delivery->id)
                ->where('delivery_assignments.status', 'accepted')
                ->where('delivery_driver_locations.recorded_at', '>=', now()->subSeconds(60))
                ->where('delivery_driver_locations.recorded_at', '<=', now())
                ->select('delivery_driver_locations.*')
                ->first();

            if ($location) {
                $driverLocation = [
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'last_updated_at' => $location->recorded_at,
                ];
            }
        }

"""
    + reader_content[end:]
)

for name, content in (("writer", writer_content), ("reader", reader_content)):
    if name == "writer" and (
        "'delivery_id' => ['required'" not in content
        or "DB::transaction(" not in content
    ):
        raise SystemExit("Writer validation failed.")
    if name == "reader" and "DB::table('delivery_driver_locations')" not in content:
        raise SystemExit("Reader validation failed.")

for path in (writer, reader):
    backup = path.with_name(
        path.name + ".backup-" + datetime.now().strftime("%Y%m%d%H%M%S")
    )
    backup.write_text(path.read_text())
    print(f"Backup: {backup}")

writer.write_text(writer_content)
reader.write_text(reader_content)

if not migration.exists() or "Schema::create('delivery_driver_locations'" not in migration.read_text():
    migration.write_text(migration_content)

print("Tracking controllers and migration updated.")
print("Migration:", migration)
