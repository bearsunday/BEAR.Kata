# `alps-reference-integrity`

**`#[Alps]` の参照が profile からずれていないか検査する** · [← 索引に戻る](../index.md)

- **Category:** Semantic / generated artifacts
- **Status:** `support`
- **Aliases:** ALPS, reference integrity, drift, SSOT, allowlist, 参照整合, ドリフト
- **Manual:** https://bearsunday.github.io/manuals/1.0/en/apidoc.html
- **Use when:** `#[Alps]` の参照が profile からずれたまま残るのを防ぎたい時。

## 例

### 走査 — `src/` から `#[Alps('id')]` を集める

正規表現でsourceを走査し、重複除去・ソートして正規化する:

```php
preg_match_all("/#\[Alps\('([^']+)'\)\]/", $contents, $matches, PREG_SET_ORDER);
foreach ($matches as [, $id]) {
    $ids[] = $id;
}
```

### 差集合 — profileに実在しないidを見つける

`var/alps/profile.json` の `descriptor[].id` と比較し、参照が実在するかを確認する:

```php
$missing = array_diff($this->referencedIds(), $this->profileIds());

$this->assertSame([], array_values(array_diff($missing, self::CACHE_SHOWCASE_GAP)));
```

### allowlist — 理由を言えるものだけ

allowlistは「黙らせる」ためではなく「意図的にprofile外」と言えるものだけを列挙する。`Cache\*` 名前空間はcache属性の見せ方に特化したshowcaseで、`profile.json` 自身の `doc` が "Covers articles, categories, tags, authors and media" と明言する本流のCMS意味論に含まれない:

```php
private const CACHE_SHOWCASE_GAP = [
    'ArticleTags',
    'AuthorProfile',
    'CacheArticlePreview',
    'doUpdateCacheArticleTags',
    'doUpdateCacheAuthor',
    'doUpdateCacheTag',
    'goAuthorProfile',
    'goCacheArticlePreview',
    'goCacheArticleTags',
    'goCacheAuthor',
    'goCacheTag',
];
```

### 逆テスト — allowlistの陳腐化を検出する

allowlistに残っているのに実際はもうprofileに存在する（＝直った）idがあれば落ちる。直したのにallowlistの行を消し忘れる事故を防ぐ:

```php
public function testCacheShowcaseGapHasNoStaleEntry(): void
{
    $missing = array_diff($this->referencedIds(), $this->profileIds());

    $this->assertSame([], array_values(array_diff(self::CACHE_SHOWCASE_GAP, $missing)));
}
```

### 空虚防止 — profileやsrc走査が壊れていないか

profileのJSONパースが壊れた、または `src/` の走査先が空になったら、上の2つのassertは両方 `[] === []` になり常にgreenになる。最低件数のlower boundで空虚化を検出する:

```php
public function testProfileAndReferencesArePopulated(): void
{
    $this->assertGreaterThan(40, count($this->profileIds()));
    $this->assertGreaterThan(40, count($this->referencedIds()));
}
```

## Naming

| 対象 | 形 | 例 |
|---|---|---|
| allowlist定数 | `<スコープ>_GAP` | `CACHE_SHOWCASE_GAP` |
| 本体test | `testEvery<検査対象><保証>` | `testEveryAlpsReferenceExistsInTheProfile` |
| 逆test | `test<AllowlistName>HasNoStaleEntry` | `testCacheShowcaseGapHasNoStaleEntry` |
| 空虚防止test | `test<走査対象>IsPopulated` | `testProfileAndReferencesArePopulated` |

allowlistはprefixパターンマッチではなく列挙にする — 新しいidがスコープ外の場所で発生してもprefixチェックだと静かに通ってしまうため、ここで一件ずつ検査が効くようにする。

> 命名規則の全容は [conventions.md](../docs/conventions.md#3-naming) を参照。

## 着手前チェック（Before）

- [ ] `npm run alps:validate`（`asd --validate var/alps/profile.json`）はprofile単体の構文しか見ない — コード側の参照はチェックしないと理解したか。リポジトリ直下の `vendor/bin/asd` は位置引数しか受けない別実装で、`--validate` を渡しても検証せず図を生成するだけ。
- [ ] allowlistに入れてよいのは「理由を1文で説明できるもの」だけと決めたか（「いま落ちているのを黙らせるため」は不可）。
- [ ] 新しい `tests/<Dir>/` を作ったら `phpunit.xml.dist` の `<testsuites>` にその directory を追加したか。登録を忘れると `vendor/bin/phpunit`（デフォルト実行）がそのディレクトリを一切走らせず、このテストは書いた瞬間から誰にも実行されない「存在しない検査」になる。

## Source

- [`var/alps/profile.json`](../var/alps/profile.json)
- [`tests/Alps/AlpsReferenceIntegrityTest.php`](../tests/Alps/AlpsReferenceIntegrityTest.php)
- [`phpunit.xml.dist`](../phpunit.xml.dist) — `tests/Alps` を `<testsuites>` に登録する側

## Tests

- [`tests/Alps/AlpsReferenceIntegrityTest.php`](../tests/Alps/AlpsReferenceIntegrityTest.php)

## Key points

`#[Alps('id')]` はResourceが「このprofile descriptorを実装している」と主張するものだが、`asd --validate` はprofile自体の構文しか見ず、コードからの参照が実在するかは検査しない。この型はsrcを走査して集めたid集合とprofileのdescriptor id集合の差集合を取り、残りをallowlistと突き合わせる。allowlistには逆方向のテストを付け（直ったのに残っていたら落ちる）、profileやsrc走査が壊れて両assertが空虚化しないよう最低件数のlower boundで守る。3段構えのどれか1つを削ると保証が崩れる。

## Do not

- allowlistを「いま落ちているものを黙らせるため」に使わない — 1件ごとに理由（なぜprofile外で正しいか）が言えるものだけを入れる。理由が言えない参照は `#[Alps]` 側を直すか、profileにdescriptorを足す。
- profileを触らずテストを緩める方向に倒さない — 参照がprofileに実在しないなら、まず「属性側の綴りが間違っているか」「profile側に真の欠落があるか」を疑い、allowlistは最後の選択肢にする。

## マスター確認（After）

- [ ] `src/` の `#[Alps]` 参照がすべてprofileに存在するか、allowlistで1件ずつ説明できる。
- [ ] `tests/Alps/AlpsReferenceIntegrityTest.php` 相当が green。

## See also

- [`alps-profile-ssot`](./alps-profile-ssot.md) — このテストが検査対象とするSSOT本体
- [`hal-link`](./hal-link.md) — Choreography idが実際に `_links` relへ接続する側
- [`hypermedia-workflow-test`](./hypermedia-workflow-test.md) — allowlist + 逆テストと同じ構造を遷移の疎通検証に使う別の型
- [`json-schema-generated`](./json-schema-generated.md) — 同じprofileから生成される別の派生物の整合性
