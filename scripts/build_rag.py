from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from backend.services.rag import get_rag_service

if __name__ == "__main__":
    count = get_rag_service().build(force=True)
    print(f"RAG knowledge base rebuilt with {count} documents.")
