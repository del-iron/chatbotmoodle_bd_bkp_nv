/**
 /*
 * Transforma a consulta do usuário em um array de palavras e identifica as palavras-chave.
 * @param {string} query - A consulta do usuário // 
 * @param {Array<string>} keywords - As palavras-chave para comparar.
 * @returns {Array<string>} - As palavras da consulta que correspondem às palavras-chave.

function processQuery(query, keywords) {
  // Transforma a consulta em um array de palavras
  const words = query.toLowerCase().split(' ');

  // Filtra as palavras que correspondem às palavras-chave
  const matchedKeywords = words.filter(word => keywords.includes(word));

  return matchedKeywords;
}

// Exemplo de uso
const userQuery = "Como configurar o docker-compose para o Moodle?";
const keywords = ["docker-compose", "Moodle", "configurar"];
const result = processQuery(userQuery, keywords);

console.log(result); // Output: ["docker-compose", "Moodle", "configurar"]
 */