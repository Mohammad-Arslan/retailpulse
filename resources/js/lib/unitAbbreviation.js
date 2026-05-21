/**
 * Derive a unit abbreviation from a display name.
 *
 * @param {string} name
 * @param {number} maxLength
 * @returns {string}
 */
export function unitAbbreviationFromName(name, maxLength = 16) {
    const cleaned = name.trim().replace(/\s+/g, ' ');

    if (!cleaned) {
        return '';
    }

    const words = cleaned.split(' ').filter(Boolean);
    let abbr;

    if (words.length >= 2) {
        abbr = words
            .map((word) => {
                const match = word.match(/[a-zA-Z0-9]/);

                return match ? match[0] : '';
            })
            .join('');
    } else {
        const word = words[0].replace(/[^a-zA-Z0-9]/g, '');

        abbr = word.length <= 6 ? word : word.slice(0, 6);
    }

    return abbr.toUpperCase().slice(0, maxLength);
}
