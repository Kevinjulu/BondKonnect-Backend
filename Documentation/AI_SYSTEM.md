# BondKonnect AI System: Technical Architecture

This document outlines the architecture, implementation, and future roadmap for the BondKonnect AI Concierge.

## 🏛 System Overview
The AI Assistant is built using a **RAG (Retrieval-Augmented Generation)** architecture. Instead of relying on general internet knowledge, the system "retrieves" relevant facts from a local knowledge base (the "Ground Truth") before generating a response.

### Core Components
1.  **Knowledge Base:** Markdown files in `storage/app/ai_knowledge/` covering UI navigation, market rules, and user guides.
2.  **Vector Store:** Neon PostgreSQL table `knowledge_chunks` using the `pgvector` extension for similarity searching.
3.  **Embedding Engine:** Currently uses Gemini's `text-embedding-004` to convert text into 1536-dimension vectors.
4.  **Reasoning Engine:** Currently uses Gemini-2.0-Flash to formulate responses based on retrieved context.

---

## 🚀 Knowledge Synchronization
To update the AI's knowledge, modify the files in `storage/app/ai_knowledge/` and rerun the seeder:

```bash
php artisan db:seed --class=KnowledgeBaseSeeder
```

The seeder:
1.  Reads all `.md` files in the storage directory.
2.  Splits them into chunks based on headers (##).
3.  Generates embeddings for each chunk via `AiService`.
4.  Updates the `knowledge_chunks` table.

---

## 🔮 Future Roadmap: Migrating to DeepSeek

The system is designed to be model-agnostic. To migrate to DeepSeek:

1.  **Obtain DeepSeek API Key:** Add it to the `.env` file.
2.  **Update `AiService` Config:**
    -   Change `baseUrl` to `https://api.deepseek.com/chat/completions`.
    -   Update the request payload to match DeepSeek's OpenAI-compatible API format.
3.  **Hybrid Strategy (Recommended):**
    -   Continue using **Gemini** for embeddings (very high performance and low cost).
    -   Use **DeepSeek-V3** or **DeepSeek-R1** for the chat completions (the "Reasoning").
    -   This avoids re-vectorizing your entire database while gaining DeepSeek's advanced reasoning capabilities.

---

## 🧪 Testing the AI
A dedicated unit test is available to verify vector retrieval:
```bash
php artisan test --filter=AiKnowledgeTest
```
This test ensures that a query like "M-Pesa" correctly retrieves the "Billing & Payments" section from the database.
