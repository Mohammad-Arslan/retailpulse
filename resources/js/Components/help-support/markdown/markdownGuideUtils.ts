export type MarkdownGuideSection = {
    id: string;
    num: string;
    title: string;
    markdown: string;
};

function slugify(text: string) {
    return text
        .trim()
        .toLowerCase()
        .replace(/[`~!@#$%^&*()+={}\[\]|\\:;"'<>,.?/]+/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

export function parseMarkdownGuide(markdown: string): {
    docTitle: string;
    sections: MarkdownGuideSection[];
} {
    const lines = markdown.split(/\r?\n/);

    const docTitle = (() => {
        const h1 = lines.find((l) => l.startsWith('# '));
        return h1 ? h1.replace(/^#\s+/, '').trim() : 'Guide';
    })();

    // Split on level-2 headings (##) to mimic “21 sections” feel.
    // Everything before the first ## becomes part of the first section if present; otherwise ignored.
    const parts: Array<{ title: string; body: string[] }> = [];
    let currentTitle: string | null = null;
    let currentBody: string[] = [];

    for (const line of lines) {
        if (line.startsWith('## ')) {
            if (currentTitle) {
                parts.push({ title: currentTitle, body: currentBody });
            }
            currentTitle = line.replace(/^##\s+/, '').trim();
            currentBody = [];
            continue;
        }

        if (currentTitle) {
            currentBody.push(line);
        }
    }

    if (currentTitle) {
        parts.push({ title: currentTitle, body: currentBody });
    }

    const sections = parts.map((p, idx) => {
        const num = String(idx + 1);
        const id = `${num}-${slugify(p.title)}`.slice(0, 80);
        return {
            id,
            num,
            title: p.title,
            markdown: `## ${p.title}\n\n${p.body.join('\n')}`.trim(),
        };
    });

    return { docTitle, sections };
}

