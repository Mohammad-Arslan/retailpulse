type Props = {
    value: string;
    onChange: (value: string) => void;
    hint?: string;
};

export default function GuideSearch({ value, onChange }: Props) {
    return (
        <input
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder="Search this guide…"
            className="w-full rounded-lg border border-[color:var(--g-border)] bg-[color:var(--g-panel)] px-3 py-2 text-[13px] text-[color:var(--g-text)] outline-none focus:border-[color:var(--g-teal-dim)]"
        />
    );
}

