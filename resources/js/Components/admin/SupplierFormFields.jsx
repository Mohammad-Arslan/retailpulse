import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const emptyContact = () => ({ name: '', email: '', phone: '', role: '', is_primary: false });
const emptyAddress = () => ({
    label: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    postal_code: '',
    country_code: '',
    is_default: false,
});

export default function SupplierFormFields({ data, setData, errors, currencies = [] }) {
    const { t } = useTranslation();

    const updateContact = (index, field, value) => {
        const contacts = [...(data.contacts ?? [])];
        contacts[index] = { ...contacts[index], [field]: value };
        setData('contacts', contacts);
    };

    const addContact = () => setData('contacts', [...(data.contacts ?? []), emptyContact()]);

    const removeContact = (index) => {
        setData(
            'contacts',
            (data.contacts ?? []).filter((_, i) => i !== index),
        );
    };

    const updateAddress = (index, field, value) => {
        const addresses = [...(data.addresses ?? [])];
        addresses[index] = { ...addresses[index], [field]: value };
        setData('addresses', addresses);
    };

    const addAddress = () => setData('addresses', [...(data.addresses ?? []), emptyAddress()]);

    const removeAddress = (index) => {
        setData(
            'addresses',
            (data.addresses ?? []).filter((_, i) => i !== index),
        );
    };

    return (
        <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <FormCard className="max-w-none w-full">
                <h3 className="rp-form-label mb-4">{t('pages.suppliers.sections.details')}</h3>
                <div className="grid gap-4 sm:grid-cols-2">
                    <AdminFormField
                        label={t('pages.suppliers.fields.code')}
                        id="code"
                        error={errors.code}
                        hint={t('pages.suppliers.fields.codeHint')}
                    >
                        <input
                            id="code"
                            className="rp-form-input w-full"
                            value={data.code ?? ''}
                            onChange={(e) => setData('code', e.target.value)}
                            placeholder={t('pages.suppliers.placeholders.code')}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.suppliers.fields.name')} id="name" error={errors.name} required>
                        <input
                            id="name"
                            className="rp-form-input w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder={t('pages.suppliers.placeholders.name')}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.suppliers.fields.email')} id="email" error={errors.email}>
                        <input
                            id="email"
                            type="email"
                            className="rp-form-input w-full"
                            value={data.email ?? ''}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder={t('pages.suppliers.placeholders.email')}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.suppliers.fields.phone')} id="phone" error={errors.phone}>
                        <input
                            id="phone"
                            className="rp-form-input w-full"
                            value={data.phone ?? ''}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder={t('pages.suppliers.placeholders.phone')}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.suppliers.fields.taxRegistrationNo')}
                        id="tax_registration_no"
                        error={errors.tax_registration_no}
                    >
                        <input
                            id="tax_registration_no"
                            className="rp-form-input w-full"
                            value={data.tax_registration_no ?? ''}
                            onChange={(e) => setData('tax_registration_no', e.target.value)}
                            placeholder={t('pages.suppliers.placeholders.taxRegistrationNo')}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.suppliers.fields.currency')}
                        id="currency_code"
                        error={errors.currency_code}
                    >
                        <Select
                            inputId="currency_code"
                            options={currencies}
                            value={data.currency_code}
                            onChange={(value) => setData('currency_code', value ?? '')}
                            placeholder={t('common.selectCurrency')}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.suppliers.fields.paymentTermsDays')}
                        id="payment_terms_days"
                        error={errors.payment_terms_days}
                    >
                        <input
                            id="payment_terms_days"
                            type="number"
                            min="0"
                            className="rp-form-input w-full"
                            value={data.payment_terms_days ?? ''}
                            onChange={(e) => setData('payment_terms_days', e.target.value)}
                            placeholder={t('pages.suppliers.placeholders.paymentTermsDays')}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.suppliers.fields.creditTermsDays')}
                        id="credit_terms_days"
                        error={errors.credit_terms_days}
                    >
                        <input
                            id="credit_terms_days"
                            type="number"
                            min="0"
                            className="rp-form-input w-full"
                            value={data.credit_terms_days ?? ''}
                            onChange={(e) => setData('credit_terms_days', e.target.value)}
                            placeholder={t('pages.suppliers.placeholders.creditTermsDays')}
                        />
                    </AdminFormField>
                </div>
                <AdminFormField label={t('pages.suppliers.fields.notes')} id="notes" error={errors.notes} className="mt-4">
                    <textarea
                        id="notes"
                        rows={3}
                        className="rp-form-input w-full"
                        value={data.notes ?? ''}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder={t('pages.suppliers.placeholders.notes')}
                    />
                </AdminFormField>
                <label className="mt-4 flex min-h-[42px] items-center gap-2 text-sm text-rp-text-secondary">
                    <input
                        type="checkbox"
                        className="h-4 w-4 rounded border-rp-border text-teal-600"
                        checked={data.is_active ?? true}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    {t('pages.suppliers.fields.active')}
                </label>
            </FormCard>

            <FormCard className="max-w-none w-full">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <h3 className="rp-form-label mb-0">{t('pages.suppliers.sections.contacts')}</h3>
                    <Button type="button" variant="outline" size="sm" onClick={addContact}>
                        <Plus className="h-4 w-4" />
                        {t('pages.suppliers.addContact')}
                    </Button>
                </div>
                {(data.contacts ?? []).length === 0 ? (
                    <button
                        type="button"
                        onClick={addContact}
                        className="flex w-full flex-col items-center justify-center rounded-lg border border-dashed border-rp-border bg-rp-surface-inset/40 px-6 py-10 text-center transition-colors hover:border-teal-500/50 hover:bg-rp-surface-inset/70"
                    >
                        <Plus className="mb-2 h-5 w-5 text-rp-text-muted" />
                        <span className="text-sm font-medium text-rp-text">{t('pages.suppliers.contactsEmpty')}</span>
                        <span className="mt-1 text-xs text-rp-text-muted">{t('pages.suppliers.contactsEmptyHint')}</span>
                    </button>
                ) : (
                    <div className="space-y-4">
                        {(data.contacts ?? []).map((contact, index) => (
                            <div
                                key={index}
                                className="space-y-3 rounded-lg border border-rp-border bg-rp-surface-inset/40 p-4"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-rp-text">
                                        {t('pages.suppliers.contactN', { n: index + 1 })}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeContact(index)}
                                        aria-label={t('common.delete')}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <AdminFormField label={t('pages.suppliers.fields.contactName')} id={`contact-name-${index}`}>
                                        <input
                                            id={`contact-name-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.contactName')}
                                            value={contact.name}
                                            onChange={(e) => updateContact(index, 'name', e.target.value)}
                                            required
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.suppliers.fields.contactRole')} id={`contact-role-${index}`}>
                                        <input
                                            id={`contact-role-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.contactRole')}
                                            value={contact.role ?? ''}
                                            onChange={(e) => updateContact(index, 'role', e.target.value)}
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.suppliers.fields.contactEmail')} id={`contact-email-${index}`}>
                                        <input
                                            id={`contact-email-${index}`}
                                            className="rp-form-input w-full"
                                            type="email"
                                            placeholder={t('pages.suppliers.placeholders.contactEmail')}
                                            value={contact.email ?? ''}
                                            onChange={(e) => updateContact(index, 'email', e.target.value)}
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.suppliers.fields.contactPhone')} id={`contact-phone-${index}`}>
                                        <input
                                            id={`contact-phone-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.contactPhone')}
                                            value={contact.phone ?? ''}
                                            onChange={(e) => updateContact(index, 'phone', e.target.value)}
                                        />
                                    </AdminFormField>
                                </div>
                                <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-rp-border text-teal-600"
                                        checked={contact.is_primary ?? false}
                                        onChange={(e) => updateContact(index, 'is_primary', e.target.checked)}
                                    />
                                    {t('pages.suppliers.fields.primaryContact')}
                                </label>
                            </div>
                        ))}
                    </div>
                )}
            </FormCard>

            <FormCard className="max-w-none w-full xl:col-span-2">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <h3 className="rp-form-label mb-0">{t('pages.suppliers.sections.addresses')}</h3>
                    <Button type="button" variant="outline" size="sm" onClick={addAddress}>
                        <Plus className="h-4 w-4" />
                        {t('pages.suppliers.addAddress')}
                    </Button>
                </div>
                {(data.addresses ?? []).length === 0 ? (
                    <button
                        type="button"
                        onClick={addAddress}
                        className="flex w-full flex-col items-center justify-center rounded-lg border border-dashed border-rp-border bg-rp-surface-inset/40 px-6 py-10 text-center transition-colors hover:border-teal-500/50 hover:bg-rp-surface-inset/70"
                    >
                        <Plus className="mb-2 h-5 w-5 text-rp-text-muted" />
                        <span className="text-sm font-medium text-rp-text">{t('pages.suppliers.addressesEmpty')}</span>
                        <span className="mt-1 text-xs text-rp-text-muted">{t('pages.suppliers.addressesEmptyHint')}</span>
                    </button>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {(data.addresses ?? []).map((address, index) => (
                            <div
                                key={index}
                                className="space-y-3 rounded-lg border border-rp-border bg-rp-surface-inset/40 p-4"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-rp-text">
                                        {t('pages.suppliers.addressN', { n: index + 1 })}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeAddress(index)}
                                        aria-label={t('common.delete')}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <AdminFormField
                                        label={t('pages.suppliers.fields.addressLabel')}
                                        id={`address-label-${index}`}
                                        className="sm:col-span-2"
                                    >
                                        <input
                                            id={`address-label-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.addressLabel')}
                                            value={address.label ?? ''}
                                            onChange={(e) => updateAddress(index, 'label', e.target.value)}
                                        />
                                    </AdminFormField>
                                    <AdminFormField
                                        label={t('pages.suppliers.fields.addressLine1')}
                                        id={`address-line1-${index}`}
                                        className="sm:col-span-2"
                                    >
                                        <input
                                            id={`address-line1-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.addressLine1')}
                                            value={address.address_line_1}
                                            onChange={(e) => updateAddress(index, 'address_line_1', e.target.value)}
                                            required
                                        />
                                    </AdminFormField>
                                    <AdminFormField
                                        label={t('pages.suppliers.fields.addressLine2')}
                                        id={`address-line2-${index}`}
                                        className="sm:col-span-2"
                                    >
                                        <input
                                            id={`address-line2-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.addressLine2')}
                                            value={address.address_line_2 ?? ''}
                                            onChange={(e) => updateAddress(index, 'address_line_2', e.target.value)}
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.suppliers.fields.city')} id={`address-city-${index}`}>
                                        <input
                                            id={`address-city-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.city')}
                                            value={address.city ?? ''}
                                            onChange={(e) => updateAddress(index, 'city', e.target.value)}
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.suppliers.fields.state')} id={`address-state-${index}`}>
                                        <input
                                            id={`address-state-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.state')}
                                            value={address.state ?? ''}
                                            onChange={(e) => updateAddress(index, 'state', e.target.value)}
                                        />
                                    </AdminFormField>
                                    <AdminFormField
                                        label={t('pages.suppliers.fields.postalCode')}
                                        id={`address-postal-${index}`}
                                    >
                                        <input
                                            id={`address-postal-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.postalCode')}
                                            value={address.postal_code ?? ''}
                                            onChange={(e) => updateAddress(index, 'postal_code', e.target.value)}
                                        />
                                    </AdminFormField>
                                    <AdminFormField label={t('pages.suppliers.fields.country')} id={`address-country-${index}`}>
                                        <input
                                            id={`address-country-${index}`}
                                            className="rp-form-input w-full"
                                            placeholder={t('pages.suppliers.placeholders.country')}
                                            maxLength={2}
                                            value={address.country_code ?? ''}
                                            onChange={(e) =>
                                                updateAddress(index, 'country_code', e.target.value.toUpperCase())
                                            }
                                        />
                                    </AdminFormField>
                                </div>
                                <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-rp-border text-teal-600"
                                        checked={address.is_default ?? false}
                                        onChange={(e) => updateAddress(index, 'is_default', e.target.checked)}
                                    />
                                    {t('pages.suppliers.fields.defaultAddress')}
                                </label>
                            </div>
                        ))}
                    </div>
                )}
            </FormCard>
        </div>
    );
}
