<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const STATUSES = ['present', 'late', 'absent'];
    protected $fillable = ['employee_id', 'attendance_date', 'status'];
    protected function casts(): array { return ['attendance_date' => 'date', 'employee_id' => 'integer']; }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
