/*
 Version: 0.1.0 (2025-05-31)
 File Description: フロント→mobes_ai API 呼び出しラッパ雛形。
*/

const MobesAiApi = (() => {
  const API_BASE = './mobes_ai/api/'; // ensure relative path

  async function post(endpoint, payload = {}) {
    const res = await fetch(API_BASE + endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'include'
    });
    return res.json();
  }

  return {
    getRecommendations: (body) => post('get_recommendations.php', body),
    addToCart: (body) => post('add_to_cart.php', body),
    saveMessage: (body) => post('save_message.php', body)
  };
})(); 