/**
 * Popisy produktov sa zadávajú v HTML editore a vykresľujú cez v-html,
 * takže musia prejsť cez bielu listinu značiek — inak by ktokoľvek s prístupom
 * do administrácie vedel do karty produktu dostať skript.
 */
const ALLOWED_TAGS = ['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'H2', 'H3', 'UL', 'OL', 'LI', 'A'];
const ALLOWED_HREF = /^(https?:\/\/|mailto:|\/)/i;

export const sanitizeHtml = (html) => {
    if (!html) return '';

    const template = document.createElement('template');
    template.innerHTML = String(html);

    const walk = (node) => {
        [...node.children].forEach((child) => {
            walk(child);

            if (!ALLOWED_TAGS.includes(child.tagName)) {
                child.replaceWith(...child.childNodes);
                return;
            }

            [...child.attributes].forEach((attribute) => {
                const name = attribute.name.toLowerCase();

                if (child.tagName === 'A' && name === 'href' && ALLOWED_HREF.test(attribute.value)) {
                    return;
                }

                child.removeAttribute(attribute.name);
            });

            if (child.tagName === 'A') {
                child.setAttribute('rel', 'noopener nofollow');
                child.setAttribute('target', '_blank');
            }
        });
    };

    walk(template.content);

    return template.innerHTML.trim();
};

/**
 * Meta description, e-maily aj výpisy v tabuľkách potrebujú čistý text.
 */
export const htmlToText = (html) =>
    String(html ?? '')
        .replace(/<(br|\/p|\/h2|\/h3|\/li)\s*\/?>/gi, ' ')
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/\s+/g, ' ')
        .trim();
