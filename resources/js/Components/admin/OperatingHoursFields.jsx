import AdminFormField from '@/Components/common/AdminFormField';

const DAYS = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
];

export default function OperatingHoursFields({ hours, onChange, errors = {} }) {
    const updateDay = (day, field, value) => {
        onChange({
            ...hours,
            [day]: {
                ...hours[day],
                [field]: value,
            },
        });
    };

    return (
        <div className="space-y-3">
            {DAYS.map((day) => (
                <div
                    key={day}
                    className="grid gap-3 rounded-lg border border-rp-border p-3 sm:grid-cols-[120px_1fr_1fr_auto]"
                >
                    <span className="text-sm font-medium capitalize text-rp-text">
                        {day}
                    </span>
                    <AdminFormField
                        label="Open"
                        id={`${day}-open`}
                        error={errors[`operating_hours.${day}.open`]}
                    >
                        <input
                            id={`${day}-open`}
                            type="time"
                            value={hours[day]?.open ?? '09:00'}
                            disabled={hours[day]?.closed}
                            className="rp-form-input"
                            onChange={(e) => updateDay(day, 'open', e.target.value)}
                        />
                    </AdminFormField>
                    <AdminFormField label="Close" id={`${day}-close`}>
                        <input
                            id={`${day}-close`}
                            type="time"
                            value={hours[day]?.close ?? '18:00'}
                            disabled={hours[day]?.closed}
                            className="rp-form-input"
                            onChange={(e) => updateDay(day, 'close', e.target.value)}
                        />
                    </AdminFormField>
                    <label className="rp-checkbox-label self-end pb-2">
                        <input
                            type="checkbox"
                            checked={hours[day]?.closed ?? false}
                            onChange={(e) =>
                                updateDay(day, 'closed', e.target.checked)
                            }
                            className="accent-teal-500"
                        />
                        Closed
                    </label>
                </div>
            ))}
        </div>
    );
}
