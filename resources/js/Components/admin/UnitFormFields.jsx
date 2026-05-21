import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import { unitAbbreviationFromName } from '@/lib/unitAbbreviation';
import { useTranslation } from 'react-i18next';

export default function UnitFormFields({ data, setData, errors }) {
    const { t } = useTranslation();

    const handleNameChange = (value) => {
        setData({
            name: value,
            abbreviation: unitAbbreviationFromName(value),
        });
    };

    return (
        <FormCard>
            <AdminFormField label={t('pages.units.fields.name')} id="name" error={errors.name}>
                <input
                    id="name"
                    value={data.name}
                    className="rp-form-input"
                    onChange={(e) => handleNameChange(e.target.value)}
                    required
                />
            </AdminFormField>
            <AdminFormField
                label={t('pages.units.fields.abbreviation')}
                id="abbreviation"
                error={errors.abbreviation}
            >
                <input
                    id="abbreviation"
                    value={data.abbreviation}
                    readOnly
                    tabIndex={-1}
                    aria-readonly="true"
                    className="rp-form-input cursor-not-allowed uppercase bg-rp-surface-inset text-rp-text-secondary"
                />
                <p className="mt-1.5 text-xs text-rp-text-muted">
                    {t('pages.units.abbreviationHint')}
                </p>
            </AdminFormField>
            <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                />
                {t('pages.units.fields.active')}
            </label>
        </FormCard>
    );
}
