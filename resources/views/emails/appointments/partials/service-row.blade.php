@if ($appointment->service)
    <tr>
        <td class="label">Service</td>
        <td>{{ $appointment->service->name }}</td>
    </tr>
@elseif ($appointment->project_type)
    <tr>
        <td class="label">Project</td>
        <td>{{ str($appointment->project_type->value)->replace('_', ' ')->title() }}</td>
    </tr>
@endif
