{**
 * Manual de uso dentro del propio módulo: lo que hay que entender antes de tocar nada, en el orden
 * en que se necesita. Vive aquí y no en un README porque quien lo necesita está en esta pantalla.
 *}
<div class="bkmd-guide">

  <section class="bkmd-guide__block">
    <h3>{l s='What this module does' d='Modules.Bkmaildesigner.Admin'}</h3>
    <p>{l s='PrestaShop sends around fifty different emails, and every module you install adds its own. This module puts your header and your footer around all of them, and lets you rewrite the ones that matter with a block editor.' d='Modules.Bkmaildesigner.Admin'}</p>
    <p>{l s='Nothing is overwritten: the original files stay where they are, untouched. The design is applied at the moment the email is sent, so you can switch it off at any time and everything goes back to how PrestaShop had it.' d='Modules.Bkmaildesigner.Admin'}</p>
    <div class="bkmd-flow">
      <div class="bkmd-flow__step"><strong>1</strong>{l s='PrestaShop prepares an email' d='Modules.Bkmaildesigner.Admin'}</div>
      <div class="bkmd-flow__arrow">&rarr;</div>
      <div class="bkmd-flow__step bkmd-flow__step--on"><strong>2</strong>{l s='This module puts it inside your design' d='Modules.Bkmaildesigner.Admin'}</div>
      <div class="bkmd-flow__arrow">&rarr;</div>
      <div class="bkmd-flow__step"><strong>3</strong>{l s='It reaches your customer' d='Modules.Bkmaildesigner.Admin'}</div>
    </div>
  </section>

  <section class="bkmd-guide__block">
    <h3>{l s='The three modes' d='Modules.Bkmaildesigner.Admin'}</h3>
    <p>{l s='Each email in the list is in one of three modes, and the name says what reaches your customer. Change it from the dropdown in its row, or tick several and use the bar above the list.' d='Modules.Bkmaildesigner.Admin'}</p>
    <div class="bkmd-modes">
      <div class="bkmd-mode-card bkmd-mode-card--original">
        <h4>{l s='Unchanged' d='Modules.Bkmaildesigner.Admin'}</h4>
        <p>{l s='The email leaves exactly as PrestaShop or the module wrote it. Nothing of yours is applied.' d='Modules.Bkmaildesigner.Admin'}</p>
        <p class="bkmd-mode-card__when">{l s='Use it for an email you already customised somewhere else, or one you would rather not touch.' d='Modules.Bkmaildesigner.Admin'}</p>
      </div>
      <div class="bkmd-mode-card bkmd-mode-card--wrapped">
        <h4>{l s='Header and footer' d='Modules.Bkmaildesigner.Admin'}</h4>
        <p>{l s='The text stays as it was; only the PrestaShop header and footer are replaced by yours. You write nothing.' d='Modules.Bkmaildesigner.Admin'}</p>
        <p class="bkmd-mode-card__when">{l s='Where an email from a module the module does not cover ends up. Enough for the ones nobody reads twice.' d='Modules.Bkmaildesigner.Admin'}</p>
      </div>
      <div class="bkmd-mode-card bkmd-mode-card--designed">
        <h4>{l s='Full design' d='Modules.Bkmaildesigner.Admin'}</h4>
        <p>{l s='Your header, your footer and a body written in the block editor: headings, text, buttons, the order table. The module ships it already written and translated for every PrestaShop email and for the modules it covers.' d='Modules.Bkmaildesigner.Admin'}</p>
        <p class="bkmd-mode-card__when">{l s='Where every PrestaShop email and every covered module email starts, from the moment you install.' d='Modules.Bkmaildesigner.Admin'}</p>
      </div>
    </div>
  </section>

  <section class="bkmd-guide__block">
    <h3>{l s='Variables: the words PrestaShop fills in' d='Modules.Bkmaildesigner.Admin'}</h3>
    <p>{l s='A variable is a word in braces that PrestaShop replaces with real data when it sends the email. If you write "Hi {firstname}," your customer reads "Hi María,". They are not decoration: type one that does not exist and your customer sees the braces.' d='Modules.Bkmaildesigner.Admin'}</p>
    <p>{l s='That is why you insert them by clicking, never by typing: the Variables panel on the left of the editor lists the ones that this particular email really receives, read from its original file and from emails already sent.' d='Modules.Bkmaildesigner.Admin'}</p>
    <p>{l s='These four work in every email:' d='Modules.Bkmaildesigner.Admin'}</p>
    <p class="bkmd-guide__vars">
      <code>{literal}{shop_name}{/literal}</code> <code>{literal}{shop_url}{/literal}</code>
      <code>{literal}{my_account_url}{/literal}</code> <code>{literal}{history_url}{/literal}</code>
    </p>
  </section>

  <section class="bkmd-guide__block">
    <h3>{l s='Languages' d='Modules.Bkmaildesigner.Admin'}</h3>
    <p>{l s='Every email is written once per language. The tabs at the top of the editor switch between them; the coloured letters in the list tell you at a glance how each language stands.' d='Modules.Bkmaildesigner.Admin'}</p>
    <ul class="bkmd-guide__list">
      <li><span class="bkmd-dot bkmd-dot--own">ES</span> {l s='It has its own text in that language.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li><span class="bkmd-dot bkmd-dot--inherited">ES</span> {l s='It has nothing of its own, so the reference language is sent instead. Nothing breaks, but a German customer reads it in the reference language.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li><span class="bkmd-dot bkmd-dot--file">ES</span> {l s='It is branded and PrestaShop has the original file in that language.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li><span class="bkmd-dot bkmd-dot--missing">ES</span> {l s='PrestaShop has no file in that language either; it falls back the way it always did.' d='Modules.Bkmaildesigner.Admin'}</li>
    </ul>
    <p>{l s='"Copy from reference" in the editor brings the reference text into the language you are on, so you only have to translate it.' d='Modules.Bkmaildesigner.Admin'}</p>
  </section>

  <section class="bkmd-guide__block">
    <h3>{l s='The order lines' d='Modules.Bkmaildesigner.Admin'}</h3>
    <p>{l s='PrestaShop sends the products of an order as a five-column table. On a phone those columns leave about eighty pixels for the product name, so the module lays them out again: the name, its combination and customisations and its «2 × 49.99 €» on the left, the line total on the right. It reads at 320 px without any media query.' d='Modules.Bkmaildesigner.Admin'}</p>
    <ul class="bkmd-guide__list">
      <li>{l s='The theme decides how it looks: hairline, alternating background or one box per product, with or without the product photo.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li>{l s='Each order block can override it for its own email: the photo, the reference, the combination and customisations and the quantity with the unit price are turned on and off one by one.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li>{l s='Any further change goes in «Your own CSS», at the bottom of the design screen: .bk-line is the line, .bk-line__name the product name, .bk-line__meta the small print and .bk-line__amount the total.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li>{l s='A module that sends its products with a different markup keeps its own table: nothing is rewritten unless it is recognised.' d='Modules.Bkmaildesigner.Admin'}</li>
    </ul>
  </section>

  <section class="bkmd-guide__block">
    <h3>{l s='Check it before you leave it running' d='Modules.Bkmaildesigner.Admin'}</h3>
    <ol class="bkmd-guide__list bkmd-guide__list--num">
      <li>{l s='The preview in the editor is rendered by the same engine that sends the emails, with your last real order. What you see is what leaves.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li>{l s='Switch to Plain text: that is what a reader with images turned off gets. It should still make sense.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li>{l s='Send yourself a test and open it on your phone, in Gmail and, if your customers use it, in Outlook. Those three cover almost everyone.' d='Modules.Bkmaildesigner.Admin'}</li>
    </ol>
    <p class="bkmd-guide__note">{l s='The blocks are built with the same table markup PrestaShop uses, so Outlook renders them properly. Avoid pasting HTML from a word processor into the HTML block: that is the one thing that breaks in Outlook.' d='Modules.Bkmaildesigner.Admin'}</p>
  </section>

  <section class="bkmd-guide__block">
    <h3>{l s='If something looks wrong' d='Modules.Bkmaildesigner.Admin'}</h3>
    <ul class="bkmd-guide__list">
      <li><strong>{l s='An email arrives without your design.' d='Modules.Bkmaildesigner.Admin'}</strong> {l s='Check that it is not in Untouched mode, and that the master switch in Settings is on. A designed email with no blocks is sent as the original on purpose: an empty email is worse than a plain one.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li><strong>{l s='You see braces in the received email.' d='Modules.Bkmaildesigner.Admin'}</strong> {l s='A variable was typed by hand that this email does not receive. Open the editor and insert it from the Variables panel.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li><strong>{l s='A new email appeared in the list.' d='Modules.Bkmaildesigner.Admin'}</strong> {l s='You installed a module that sends emails. The list finds them on its own; the Settings tab decides whether they arrive branded or untouched.' d='Modules.Bkmaildesigner.Admin'}</li>
      <li><strong>{l s='You want everything back as it was.' d='Modules.Bkmaildesigner.Admin'}</strong> {l s='Turn off the master switch in Settings. Your designs are kept, waiting. Uninstalling the module leaves no trace either.' d='Modules.Bkmaildesigner.Admin'}</li>
    </ul>
    <p>{l s='Turn on the debug log in Settings and you get one line per email sent, saying which template it was and which mode was applied.' d='Modules.Bkmaildesigner.Admin'}</p>
  </section>
</div>
