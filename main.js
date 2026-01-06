/**
 * AI API Client pro frontend
 * Jednoduchý klient - pouze přeposílá data do API
 */
class AiApiClient {
    constructor(baseUrl = './src/AI/API/Api.php') {
        this.baseUrl = baseUrl;
    }

    /**
     * Odešle všechna data z formuláře do API
     * API si samo rozparsuje _ai_* fieldy
     */
    async sendFormData(formData) {
        const response = await fetch(this.baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        return data;
    }
}

/**
 * Helper pro extrakci dat z formuláře
 */
function extractFormData(form) {
    return Array.from(form.elements)
        .filter(el => el.name && !el.disabled && el.type !== 'button')
        .reduce((data, el) => {
            data[el.name] =
                el.type === 'checkbox' ? el.checked :
                el.type === 'radio' ? (el.checked ? el.value : data[el.name]) :
                el.value;
            return data;
        }, {});
}

// Inicializace
document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('.ai-generate-button');
    const result = document.querySelector('.generator-result');

    const aiClient = new AiApiClient();

    button.addEventListener('click', async (e) => {
        e.preventDefault();

        const form = button.closest('form');
        const formData = extractFormData(form);

        try {
            const response = await aiClient.sendFormData(formData);

			console.log(response);
			result.innerHTML = response.data;

		} catch (error) {
            console.error('AI Error:', error);
        } finally {
            console.error('Generování dokončeno.');
        }
    });
});