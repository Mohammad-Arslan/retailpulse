import { useEffect, useRef, useState } from 'react';
import { pinApi } from '@/lib/posApi';

export function PinModal({ lockout, onVerified }) {
    const [digits, setDigits] = useState(['', '', '', '', '', '']);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);
    const inputRefs = useRef([]);

    useEffect(() => {
        inputRefs.current[0]?.focus();
    }, []);

    const isLocked = lockout?.is_locked;

    function handleDigitChange(index, value) {
        if (!/^\d?$/.test(value)) return;
        const next = [...digits];
        next[index] = value;
        setDigits(next);

        if (value && index < 5) {
            inputRefs.current[index + 1]?.focus();
        }

        if (next.every((d) => d !== '')) {
            submitPin(next.join(''));
        }
    }

    function handleKeyDown(index, e) {
        if (e.key === 'Backspace' && !digits[index] && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }
    }

    async function submitPin(pin) {
        setLoading(true);
        setError(null);

        try {
            const res = await pinApi.verify(pin);
            if (res.verified) {
                onVerified();
            } else {
                setError('Incorrect PIN.');
                setDigits(['', '', '', '', '', '']);
                inputRefs.current[0]?.focus();
            }
        } catch (err) {
            const msg =
                err?.response?.data?.errors?.pin?.[0] ||
                err?.response?.data?.message ||
                'Verification failed.';
            setError(msg);
            setDigits(['', '', '', '', '', '']);
            inputRefs.current[0]?.focus();
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
            <div className="w-full max-w-sm rounded-2xl bg-white p-8 shadow-2xl dark:bg-zinc-900">
                <div className="mb-6 text-center">
                    <div className="mb-2 text-4xl">🔐</div>
                    <h2 className="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                        POS PIN Required
                    </h2>
                    <p className="mt-1 text-sm text-zinc-500">
                        Enter your 6-digit PIN to access the POS
                    </p>
                </div>

                {isLocked ? (
                    <div className="rounded-lg bg-red-50 p-4 text-center text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                        <p className="font-medium">Account locked</p>
                        <p className="mt-1">
                            Too many failed attempts. Try again in{' '}
                            <span className="font-bold">
                                {lockout.minutes_remaining} min
                            </span>
                            .
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="mb-4 flex justify-center gap-3">
                            {digits.map((digit, i) => (
                                <input
                                    key={i}
                                    ref={(el) => (inputRefs.current[i] = el)}
                                    type="password"
                                    inputMode="numeric"
                                    maxLength={1}
                                    value={digit}
                                    onChange={(e) => handleDigitChange(i, e.target.value)}
                                    onKeyDown={(e) => handleKeyDown(i, e)}
                                    disabled={loading}
                                    className="h-12 w-12 rounded-lg border-2 border-zinc-300 text-center text-xl font-bold focus:border-blue-500 focus:outline-none disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                                />
                            ))}
                        </div>

                        {error && (
                            <p className="mb-2 text-center text-sm text-red-600 dark:text-red-400">
                                {error}
                            </p>
                        )}

                        {loading && (
                            <p className="text-center text-sm text-zinc-500">
                                Verifying…
                            </p>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
