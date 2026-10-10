// Endereço base da API (backend PHP no XAMPP)
const API_BASE_URL = 'http://localhost:8080/senai_conecta/backend';

// Função única para chamar a API: monta cabeçalhos, envia e trata erros
async function apiFetch(endpoint, options = {}) {
    const token = localStorage.getItem('token');
    const headers = options.headers || {};

    // Logado: envia o token no cabeçalho Authorization
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    // JSON precisa do Content-Type; com FormData (upload) o navegador define sozinho
    if (!(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    // Faz a requisição
    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
        ...options,
        headers
    });

    // Lê a resposta; se o status for de erro, lança exceção com a mensagem do backend
    const data = await response.json();
    if (!response.ok) {
        throw new Error(data.erro || 'Erro no processamento da requisição.');
    }
    return data;
}
