type AITextProps = {
  text: string
  className?: string
}

function cleanAIText(text: string) {
  return text
    .replace(/```mermaid[\s\S]*?```/gi, '\n')
    .replace(/```[a-zA-Z0-9_-]*\s*/g, '')
    .replace(/```/g, '')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/&nbsp;/gi, ' ')
    .replace(/<\/?[^>\n]+>/g, '')
    .replace(/^\s{0,3}#{1,6}\s*/gm, '')
    .replace(/\*\*/g, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

function isListLine(line: string) {
  return /^\s*(?:[-•]|\d+[.、])\s+/.test(line)
}

function stripListMarker(line: string) {
  return line.replace(/^\s*(?:[-•]|\d+[.、])\s+/, '').trim()
}

export default function AIText({ text, className = '' }: AITextProps) {
  const cleaned = cleanAIText(text)
  if (!cleaned) return <div className={className}>暂无可展示内容</div>

  const blocks = cleaned.split(/\n{2,}/).filter(Boolean)
  return (
    <div className={`space-y-3 ${className}`}>
      {blocks.map((block, index) => {
        const lines = block.split('\n').map((line) => line.trim()).filter(Boolean)
        if (lines.length > 1 && lines.every(isListLine)) {
          return (
            <ul key={`${block}-${index}`} className="space-y-1 pl-4">
              {lines.map((line) => (
                <li key={line} className="list-disc">
                  {stripListMarker(line)}
                </li>
              ))}
            </ul>
          )
        }

        if (lines.length === 1 && /^.{2,18}[：:]?$/.test(lines[0])) {
          return <div key={`${block}-${index}`} className="font-black text-neon">{lines[0].replace(/[：:]$/, '')}</div>
        }

        return (
          <p key={`${block}-${index}`} className="whitespace-pre-line">
            {lines.join('\n')}
          </p>
        )
      })}
    </div>
  )
}
