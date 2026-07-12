import os
from dotenv import load_dotenv
from openai import OpenAI

load_dotenv()

client = OpenAI(
    api_key=os.environ["ARK_API_KEY"],
    base_url=os.environ["ARK_OPENAI_BASE_URL"],
)

response = client.responses.create(
    model=os.environ["ARK_MODEL"],
    input="请只回复：ARK API 测试成功",
)

print(response.output_text)
