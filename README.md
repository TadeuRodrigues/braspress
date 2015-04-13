braspress
=========

Módulo de frete para a Braspress.

Um *fork* do módulo http://github.com/TadeuRodrigues/braspress, incrementado com algumas funcionalidades:

- *Cálculo do peso cubado*: se os produtos tiverem os atributos *width*, *height* e *length*, será ignorado o peso original do produto e obtido o peso cubado, ou seja, o novo peso é calculado de acordo com as regras de cálculo da Braspress. Multiplica-se os valores da largura, algura e comprimento com a densidade, que é um valor padrão para os modais *rodoviário* (300) e *aéreo* (167).

- *Valor do frete como um percentual sobre o valor do pedido*: é possível definir como valor do frete um percentual sobre o valor da compra ou o retornado pelo *web service* da Braspress. Caso a opção "percentual" seja escolhida, o percentual pode ser configurado em um valor de 0,01 a 99,99%.
