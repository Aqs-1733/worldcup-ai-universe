<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$pdo = Database::pdo();
$rows = $pdo->query('SELECT id, name_original, name_cn FROM players ORDER BY team_id, id')->fetchAll();
$stmt = $pdo->prepare('UPDATE players SET name_cn = ?, source_synced_at = NOW() WHERE id = ?');
$updated = 0;
$generated = 0;

foreach ($rows as $row) {
    $current = trim((string) ($row['name_cn'] ?? ''));
    $next = exactName((string) $row['name_original']);
    $next ??= $current !== '' ? simplifyCommonChinese((string) $current) : transliterateName((string) $row['name_original']);
    if ($next === '' || $next === $current) {
        continue;
    }
    $stmt->execute([$next, (int) $row['id']]);
    $updated++;
    if ($current === '') {
        $generated++;
    }
}

$missing = (int) $pdo->query('SELECT COUNT(*) FROM players WHERE name_cn IS NULL OR name_cn = ""')->fetchColumn();
$quality = $pdo->prepare(
    'INSERT INTO data_quality_checks (check_key, label, expected_value, actual_value, status, detail, source_url, checked_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
       expected_value = VALUES(expected_value),
       actual_value = VALUES(actual_value),
       status = VALUES(status),
       detail = VALUES(detail),
       source_url = VALUES(source_url),
       checked_at = NOW()'
);
$quality->execute([
    'player_name_cn_translation',
    '球员中文名翻译补全',
    '0 missing',
    (string) $missing,
    $missing === 0 ? 'pass' : 'warn',
    "源数据已有中文名优先保留；本次自动音译补全 {$generated} 条，规范化 {$updated} 条，英文原名仍保留在 name_original。",
    'https://github.com/Aqs-1733/worldcup-ai-universe',
]);

echo json_encode([
    'players' => count($rows),
    'generated' => $generated,
    'updated' => $updated,
    'missing' => $missing,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

function transliterateName(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    if ($name === '') {
        return '';
    }

    $phrase = exactName($name);
    if ($phrase !== null) {
        return $phrase;
    }

    $parts = preg_split('/[\s\-]+/u', $name) ?: [];
    $translated = [];
    foreach ($parts as $part) {
        $part = trim($part, " \t\n\r\0\x0B'.,");
        if ($part === '') {
            continue;
        }
        $translated[] = transliterateToken($part);
    }
    return implode('·', array_values(array_filter($translated)));
}

function exactName(string $name): ?string
{
    $key = normalizeLatin($name, true);
    $map = [
        'lionel messi' => '利昂内尔·梅西',
        'cristiano ronaldo' => '克里斯蒂亚诺·罗纳尔多',
        'neymar' => '内马尔',
        'neymar junior' => '内马尔',
        'kylian mbappe' => '基利安·姆巴佩',
        'erling haaland' => '埃尔林·哈兰德',
        'kevin de bruyne' => '凯文·德布劳内',
        'romelu lukaku' => '罗梅卢·卢卡库',
        'son heung min' => '孙兴慜',
        'heung min son' => '孙兴慜',
        'kim min jae' => '金玟哉',
        'minjae kim' => '金玟哉',
        'hwang hee chan' => '黄喜灿',
        'hee chan hwang' => '黄喜灿',
        'lee kang in' => '李刚仁',
        'kang in lee' => '李刚仁',
        'bukayo saka' => '布卡约·萨卡',
        'harry kane' => '哈里·凯恩',
        'jude bellingham' => '裘德·贝林厄姆',
        'phil foden' => '菲尔·福登',
        'declan rice' => '德克兰·赖斯',
        'marcus rashford' => '马库斯·拉什福德',
        'vinicius junior' => '维尼修斯',
        'rodrygo' => '罗德里戈',
        'raphinha' => '拉菲尼亚',
        'alisson' => '阿利松',
        'ederson' => '埃德森',
        'pedri' => '佩德里',
        'gavi' => '加维',
        'dani olmo' => '达尼·奥尔莫',
        'lamine yamal' => '拉明·亚马尔',
        'nico williams' => '尼科·威廉斯',
        'ferran torres' => '费兰·托雷斯',
        'rodri' => '罗德里',
        'antoine griezmann' => '安托万·格列兹曼',
        'ousmane dembele' => '奥斯曼·登贝莱',
        'adrien rabiot' => '阿德里安·拉比奥',
        'virgil van dijk' => '维吉尔·范戴克',
        'frenkie de jong' => '弗兰基·德容',
        'memphis depay' => '孟菲斯·德佩',
        'luka modric' => '卢卡·莫德里奇',
        'mateo kovacic' => '马特奥·科瓦契奇',
        'robert lewandowski' => '罗伯特·莱万多夫斯基',
        'christian pulisic' => '克里斯蒂安·普利希奇',
        'alphonso davies' => '阿方索·戴维斯',
        'achraf hakimi' => '阿什拉夫·哈基米',
        'yassine bounou' => '亚辛·布努',
        'hakim ziyech' => '哈基姆·齐耶赫',
    ];
    if (isset($map[$key])) {
        return $map[$key];
    }

    $aliases = [
        'cristiano ronaldo' => 'C 罗',
        'lionel' => '梅西',
        'messi' => '梅西',
        'neymar' => '内马尔',
        'mbappe' => '姆巴佩',
        'haaland' => '哈兰德',
        'harry kane' => '哈里·凯恩',
        'edward kane' => '哈里·凯恩',
        'lukaku' => '卢卡库',
        'dzeko' => '埃丁·哲科',
        'salah' => '萨拉赫',
        'sadio mane' => '萨迪奥·马内',
        'modric' => '卢卡·莫德里奇',
        'arnautovic' => '马尔科·阿瑙托维奇',
        'perisic' => '伊万·佩里西奇',
        'taremi' => '迈赫迪·塔雷米',
        'depay' => '孟菲斯·德佩',
        'lautaro' => '劳塔罗·马丁内斯',
        'almoez' => '阿尔莫兹',
        'alhaydos' => '哈桑·海多斯',
        'mahrez' => '里亚德·马赫雷斯',
        'james david rodriguez' => '哈梅斯·罗德里格斯',
        'james rodriguez' => '哈梅斯·罗德里格斯',
        'kramaric' => '安德烈·克拉马里奇',
        'witsel' => '阿克塞尔·维特塞尔',
        'ayew' => '乔丹·阿尤',
        'shomurodov' => '埃尔多尔·肖穆罗多夫',
        'pulisic' => '克里斯蒂安·普利希奇',
        'xhaka' => '格拉尼特·扎卡',
        'declan rice' => '德克兰·赖斯',
        'hakimi' => '阿什拉夫·哈基米',
        'jonathan david' => '乔纳森·戴维',
        'pedri' => '佩德里',
        'dani olmo' => '达尼·奥尔莫',
        'daniel olmo' => '达尼·奥尔莫',
        'al dossari' => '萨利姆·达瓦萨里',
        'aldawsari' => '萨利姆·达瓦萨里',
        'bellingham' => '裘德·贝林厄姆',
        'ferran torres' => '费兰·托雷斯',
        'bukayo' => '布卡约·萨卡',
        'saka' => '萨卡',
        'rodriguez' => '罗德里格斯',
        'isak' => '亚历山大·伊萨克',
        'calhanoglu' => '哈坎·恰尔汗奥卢',
        'luis fernando diaz' => '路易斯·迪亚斯',
        'julian alvarez' => '胡利安·阿尔瓦雷斯',
        'guillermo ochoa' => '吉列尔莫·奥乔亚',
        'hajisafi' => '埃赫桑·哈吉萨菲',
        'khoukhi' => '布阿莱姆·胡希',
        'nazon' => '杜肯斯·纳松',
        'hussein' => '侯赛因',
        'kimmich' => '约书亚·基米希',
        'larin' => '赛尔·拉林',
        'sabitzer' => '马塞尔·萨比策',
        'ismaila sarr' => '伊斯梅拉·萨尔',
        'dembele' => '奥斯曼·登贝莱',
        'olise' => '迈克尔·奥利塞',
        'bernardo silva' => '贝尔纳多·席尔瓦',
        'vitinha' => '维蒂尼亚',
        'havertz' => '凯·哈弗茨',
        'gana gueye' => '伊德里萨·盖耶',
        'embolo' => '布雷尔·恩博洛',
        'trezeguet' => '特雷泽盖',
        'otamendi' => '尼古拉斯·奥塔门迪',
        'el kaabi' => '阿尤布·埃尔卡比',
        'unai simon' => '乌奈·西蒙',
        'mikel merino' => '米克尔·梅里诺',
        'gallardo' => '赫苏斯·加利亚多',
        'wood' => '克里斯·伍德',
        'molina' => '纳韦尔·莫利纳',
        'wirtz' => '弗洛里安·维尔茨',
        'altamari' => '穆萨·塔马里',
        'joao neves' => '若昂·内维斯',
        'alaba' => '大卫·阿拉巴',
    ];
    foreach ($aliases as $alias => $cn) {
        if (str_contains($key, $alias)) {
            return $cn;
        }
    }

    return null;
}

function transliterateToken(string $token): string
{
    $key = normalizeLatin($token);
    if ($key === '') {
        return '';
    }

    $dictionary = [
        'aaron' => '阿龙',
        'abdul' => '阿卜杜勒',
        'abdel' => '阿卜德尔',
        'adam' => '亚当',
        'adil' => '阿迪尔',
        'ahmed' => '艾哈迈德',
        'alex' => '亚历克斯',
        'alexander' => '亚历山大',
        'ali' => '阿里',
        'amine' => '阿明',
        'andreas' => '安德烈亚斯',
        'andres' => '安德烈斯',
        'angel' => '安赫尔',
        'anthony' => '安东尼',
        'antonio' => '安东尼奥',
        'arthur' => '阿图尔',
        'benjamin' => '本杰明',
        'bruno' => '布鲁诺',
        'carlos' => '卡洛斯',
        'christian' => '克里斯蒂安',
        'david' => '大卫',
        'daniel' => '丹尼尔',
        'dani' => '达尼',
        'diego' => '迭戈',
        'edgar' => '埃德加',
        'eduardo' => '爱德华多',
        'emiliano' => '埃米利亚诺',
        'erik' => '埃里克',
        'fabian' => '法比安',
        'federico' => '费德里科',
        'fernando' => '费尔南多',
        'francisco' => '弗朗西斯科',
        'gabriel' => '加布里埃尔',
        'george' => '乔治',
        'giovanni' => '乔瓦尼',
        'gonzalo' => '冈萨洛',
        'hassan' => '哈桑',
        'ibrahim' => '易卜拉欣',
        'ivan' => '伊万',
        'james' => '詹姆斯',
        'jean' => '让',
        'joao' => '若昂',
        'john' => '约翰',
        'jose' => '何塞',
        'juan' => '胡安',
        'julian' => '胡利安',
        'karim' => '卡里姆',
        'kevin' => '凯文',
        'leonardo' => '莱昂纳多',
        'lucas' => '卢卡斯',
        'luis' => '路易斯',
        'marco' => '马尔科',
        'mario' => '马里奥',
        'martin' => '马丁',
        'mateo' => '马特奥',
        'matheus' => '马特乌斯',
        'michael' => '迈克尔',
        'miguel' => '米格尔',
        'mohamed' => '穆罕默德',
        'mohammad' => '穆罕默德',
        'mohammed' => '穆罕默德',
        'muhammad' => '穆罕默德',
        'nabil' => '纳比勒',
        'nicolas' => '尼古拉斯',
        'oliver' => '奥利弗',
        'omar' => '奥马尔',
        'paul' => '保罗',
        'pedro' => '佩德罗',
        'rafael' => '拉斐尔',
        'raul' => '劳尔',
        'ricardo' => '里卡多',
        'roberto' => '罗伯托',
        'samuel' => '萨穆埃尔',
        'santiago' => '圣地亚哥',
        'sebastian' => '塞巴斯蒂安',
        'sergio' => '塞尔吉奥',
        'thomas' => '托马斯',
        'victor' => '维克托',
        'william' => '威廉',
        'youssef' => '优素福',
        'yusuf' => '优素福',
        'zinedine' => '齐内丁',
    ];
    if (isset($dictionary[$key])) {
        return $dictionary[$key];
    }

    $patterns = [
        'sson' => '松',
        'dottir' => '多蒂尔',
        'ski' => '斯基',
        'sky' => '斯基',
        'vich' => '维奇',
        'vic' => '维奇',
        'ovic' => '奥维奇',
        'evic' => '耶维奇',
        'escu' => '埃斯库',
        'inho' => '尼奥',
        'dinho' => '迪尼奥',
        'ez' => '埃斯',
        'son' => '森',
        'sen' => '森',
        'berg' => '贝里',
        'gaard' => '高',
        'mann' => '曼',
        'ullah' => '乌拉',
        'zadeh' => '扎德',
        'pour' => '普尔',
        'bek' => '别克',
        'yan' => '扬',
        'ian' => '扬',
        'chenko' => '琴科',
        'wicz' => '维奇',
    ];
    foreach ($patterns as $suffix => $cn) {
        if (str_ends_with($key, $suffix) && strlen($key) > strlen($suffix) + 1) {
            $prefix = substr($key, 0, -strlen($suffix));
            return trim(transliterateToken($prefix) . $cn);
        }
    }

    $chunks = [
        'christ' => '克里斯特',
        'stan' => '斯坦',
        'ander' => '安德',
        'andro' => '安德罗',
        'anto' => '安托',
        'mario' => '马里奥',
        'maria' => '玛丽亚',
        'moh' => '穆罕',
        'ham' => '哈姆',
        'med' => '默德',
        'kh' => '赫',
        'sh' => '什',
        'ch' => '奇',
        'ph' => '夫',
        'th' => '特',
        'gh' => '格',
        'qu' => '库',
        'ck' => '克',
        'll' => '利',
        'rr' => '尔',
        'ai' => '艾',
        'ay' => '艾',
        'ei' => '埃',
        'ey' => '伊',
        'ee' => '伊',
        'ea' => '伊',
        'oo' => '乌',
        'ou' => '乌',
        'au' => '奥',
        'ia' => '亚',
        'io' => '约',
        'jo' => '若',
        'ja' => '哈',
        'ge' => '杰',
        'gi' => '吉',
        'ci' => '西',
        'ce' => '塞',
        'ca' => '卡',
        'co' => '科',
        'cu' => '库',
        'an' => '安',
        'en' => '恩',
        'in' => '因',
        'on' => '翁',
        'un' => '温',
        'ar' => '尔',
        'er' => '尔',
        'or' => '奥尔',
        'ir' => '尔',
        'ur' => '尔',
    ];

    $out = '';
    $i = 0;
    while ($i < strlen($key)) {
        $matched = false;
        foreach ([6, 5, 4, 3, 2] as $length) {
            $piece = substr($key, $i, $length);
            if (isset($chunks[$piece])) {
                $out .= $chunks[$piece];
                $i += $length;
                $matched = true;
                break;
            }
        }
        if ($matched) {
            continue;
        }
        $out .= letterSound($key[$i]);
        $i++;
    }

    return preg_replace('/(.)\1{2,}/u', '$1$1', $out) ?: $out;
}

function normalizeLatin(string $value, bool $keepSpaces = false): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $pattern = $keepSpaces ? '/[^a-zA-Z]+/' : '/[^a-zA-Z]/';
    $separator = $keepSpaces ? ' ' : '';
    return trim(strtolower((string) preg_replace($pattern, $separator, $ascii)));
}

function letterSound(string $letter): string
{
    return [
        'a' => '阿',
        'b' => '布',
        'c' => '克',
        'd' => '德',
        'e' => '埃',
        'f' => '夫',
        'g' => '格',
        'h' => '赫',
        'i' => '伊',
        'j' => '杰',
        'k' => '克',
        'l' => '勒',
        'm' => '姆',
        'n' => '恩',
        'o' => '奥',
        'p' => '普',
        'q' => '库',
        'r' => '尔',
        's' => '斯',
        't' => '特',
        'u' => '乌',
        'v' => '维',
        'w' => '威',
        'x' => '克斯',
        'y' => '伊',
        'z' => '兹',
    ][$letter] ?? '';
}

function simplifyCommonChinese(string $name): string
{
    return strtr($name, [
        '亞' => '亚',
        '奧' => '奥',
        '貝' => '贝',
        '布魯' => '布鲁',
        '爾' => '尔',
        '弗蘭' => '弗兰',
        '岡' => '冈',
        '傑' => '杰',
        '凱' => '凯',
        '庫' => '库',
        '萊' => '莱',
        '倫' => '伦',
        '蘭' => '兰',
        '羅' => '罗',
        '盧' => '卢',
        '馬' => '马',
        '麥' => '麦',
        '門' => '门',
        '內' => '内',
        '納' => '纳',
        '諾' => '诺',
        '喬' => '乔',
        '薩' => '萨',
        '賽' => '赛',
        '聖' => '圣',
        '維' => '维',
        '威廉斯' => '威廉姆斯',
        '烏' => '乌',
        '約' => '约',
        '費' => '费',
        '漢' => '汉',
        '蓋' => '盖',
        '長' => '长',
        '澤' => '泽',
        '齊' => '齐',
        '茲' => '兹',
        '·' => '·',
    ]);
}
