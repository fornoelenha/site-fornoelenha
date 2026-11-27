# Guia do blog da Forno & Lenha (para o post diário)

## Como publicar um post
1. Ler os posts que já existem em `blog/_posts/` para não repetir assunto nem título.
2. Escolher o próximo assunto da lista "Pauta" abaixo que ainda não foi usado (ou um assunto novo dentro dos mesmos temas).
3. Criar `blog/_posts/AAAA-MM-DD-slug.md` com a data de hoje (fuso de São Paulo). Slug em minúsculas, sem acento, com hífens, até 6 palavras.
4. Rodar `python3 tools/build_blog.py` na raiz do repositório.
5. Conferir que `blog/<slug>/index.html` foi criado e que o post aparece em `blog/index.html` e no `sitemap.xml`.
6. Commit com a mensagem `Blog: <título curto>` e push na branch `main`. A Hostinger publica sozinha.

## Cabeçalho obrigatório
```
---
titulo: (até 70 caracteres, com a palavra-chave principal no começo)
descricao: (entre 120 e 160 caracteres; é o texto que aparece no Google)
data: AAAA-MM-DD
categoria: Curiosidades | Receitas | Guia do mel | Da serra catarinense | Abelhas
mel: silvestre | organico | melato   (o mel que combina com o assunto; deixe vazio se não combinar)
imagem: img/<arquivo da lista abaixo>
---
```

## Regras de conteúdo
- Português do Brasil, tom de conversa, frases curtas, como a marca fala no site. Leitor: consumidor comum.
- Entre 600 e 900 palavras. Abrir com um parágrafo que responde direto à pergunta do título.
- Estrutura com `##` subtítulos, listas quando ajudar, e um resumo curto no fim.
- Uma palavra-chave principal por post (ex.: "mel cristalizado", "melato da bracatinga") no título, na descrição, no primeiro parágrafo e em um subtítulo. Sem repetir à força.
- Pode citar os produtos de forma natural (Mel Silvestre, Silvestre Orgânico, Melato da Bracatinga), sem virar anúncio. O convite para comprar já aparece sozinho no fim do post.
- Fatos só quando houver certeza. Na dúvida, pesquisar antes ou deixar de fora. Números (temperaturas, datas, quantidades) só se confirmados.
- SAÚDE: nada de promessa de cura, tratamento ou benefício medicinal ("cura gripe", "baixa a glicose", "emagrece"). Pode falar de sabor, uso na cozinha, tradição e curiosidades. Sempre que falar de crianças, lembrar que mel não deve ser dado a menores de 1 ano.
- Nunca inventar depoimentos, avaliações, clientes, prêmios, números de vendas ou histórias da família. Sobre a Forno & Lenha, usar só os fatos abaixo.
- Receitas: ingredientes com quantidades e modo de preparo em passos numerados. Receitas sem carne combinam melhor com a marca.
- Não copiar textos de outros sites. Escrever do zero.

## Fatos da marca (pode usar)
- Forno & Lenha Alimentos: empresa de família; mel produzido em Santa Catarina e vendido em São Paulo e todo o Brasil.
- Méis: Mel Silvestre (rótulo vermelho, o clássico), Mel Silvestre Orgânico (selo Orgânico Brasil e Produto Ecológico, rótulo verde) e Melato da Bracatinga (escuro, intenso, típico da serra catarinense).
- Selos: Selo ARTE, Selo SIM Joinville, Produto Catarinense; o Orgânico tem Orgânico Brasil e Produto Ecológico.
- Embalagens: bisnaga 250g, bisnaga 500g, pote 500g e pote 1kg.
- Entrega: na capital de SP, entrega própria (R$ 20, grátis a partir de R$ 100); fora da capital, frete calculado no site.
- Mascote: o Lenha (um pedaço de lenha com bigode e foguinho).
- Pedidos pelo carrinho do site, que finaliza no WhatsApp.

## Imagens disponíveis (campo imagem)
img/jornada-florada.jpg (mata de araucárias florida) · img/jornada-abelha.jpg (abelha na flor) · img/jornada-colmeia.jpg (favos com abelhas) · img/jornada-colheita.jpg (apicultor com quadro de favos) · img/cena-pote500.jpg (três potes na mesa) · img/cena-pote1kg.jpg · img/cena-bis250.jpg · img/cena-bis500.jpg · img/receita-torrada.jpg · img/receita-iogurte.jpg · img/receita-brie.jpg · img/receita-coalho.jpg · img/receita-molho.jpg · img/receita-cha.jpg · img/receita-bolo.jpg · img/receita-banana.jpg
Variar a imagem entre posts seguidos.

## Pauta (usar de cima para baixo, pulando o que já saiu)
1. O que é melato da bracatinga e por que ele é tão escuro
2. Mel silvestre: o que significa "silvestre" no rótulo
3. Mel orgânico: o que o selo Orgânico Brasil garante
4. Como guardar mel do jeito certo (e por que não na geladeira)
5. Mel tem validade? O que dizem a ciência e a tradição
6. Por que mel não pode ser dado a bebês menores de 1 ano
7. Bisnaga ou pote: qual embalagem de mel escolher
8. 5 jeitos de usar mel no café da manhã
9. Receita: molho de mostarda e mel para salada
10. Como as abelhas fazem o mel, do néctar ao favo
11. A bracatinga: a árvore da serra catarinense por trás do melato
12. Mel escuro x mel claro: o que muda no sabor
13. Receita: queijo coalho com mel na brasa
14. Como saber se o mel é puro: o que funciona e o que é mito
15. O que é o Selo ARTE e o que ele significa para produtos artesanais
16. A vida de uma abelha operária em 6 fases
17. Receita: chá de gengibre, limão e mel
18. Harmonização: queijos que combinam com cada tipo de mel
19. Mel na confeitaria: como substituir açúcar por mel em receitas
20. Florada: por que o mel muda de cor e sabor ao longo do ano
21. Receita: brie assado com melato e nozes
22. Apicultura em Santa Catarina: um pouco da história
23. Própolis, geleia real e pólen: o que cada um é
24. Receita: banana caramelizada com mel
25. Mel e café: dá certo? Como adoçar sem amargar
26. Polinização: por que as abelhas importam para a comida no seu prato
27. Receita: bolo de mel com especiarias
28. Como dar mel de presente (ideias para datas especiais)
29. Mel em drinks e bebidas sem álcool
30. Abelhas sem ferrão x abelhas europeias: qual a diferença
31. Receita: bowl de iogurte com frutas e mel
32. O que significa "mel cru" e "mel puro" no rótulo
33. Mel na marinada: legumes e tofu assados com mel
34. Por que o mel escorre devagar (e por que isso muda com a temperatura)
35. Glossário do mel: favo, opérculo, melgueira e outras palavras do apicultor
