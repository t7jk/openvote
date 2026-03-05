body { font-family: Arial, sans-serif; color: #2c2c2c; background: #f5f5f5; margin: 0; padding: 20px; }
  .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
  .header { background: #1a3c6e; color: #ffffff; padding: 32px 40px; text-align: center; }
  .header h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: 0.5px; }
  .header p { margin: 8px 0 0; font-size: 14px; opacity: 0.8; }
  .header p.header__tagline { margin-top: 4px; font-size: 13px; opacity: 0.9; }
  .header p:empty { display: none; }
  .body { padding: 36px 40px; }
  .body p { line-height: 1.7; font-size: 15px; }
  .poll-title { font-size: 18px; font-weight: 700; color: #1a3c6e; margin: 16px 0; }
  .questions { background: #f0f4fa; border-left: 4px solid #1a3c6e; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
  .questions h3 { margin: 0 0 10px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
  .questions ul { margin: 0; padding-left: 18px; }
  .questions ul li { margin-bottom: 6px; font-size: 15px; }
  .deadline { font-size: 14px; color: #666; margin: 16px 0; }
  .deadline strong { color: #c0392b; }
  .cta { text-align: center; margin: 28px 0; }
  .cta a { background: #1a3c6e; color: #ffffff; padding: 14px 36px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 600; display: inline-block; letter-spacing: 0.3px; }
  .footer { text-align: center; padding: 20px 40px; background: #f5f5f5; font-size: 13px; color: #999; border-top: 1px solid #e0e0e0; }



<div class="wrapper">
  <div class="header">
    <h1>Zaproszenie do głosowania</h1>
    <p>{brand_short} — {site_name}</p>
    <p class="header__tagline">{site_tagline}</p>
  </div>
  <div class="body">
    <p>Szanowni Państwo,</p>
    <p>mamy zaszczyt zaprosić Państwa do udziału w głosowaniu elektronicznym:</p>
    <div class="poll-title">„{poll_title}"</div>
    <div class="questions">
      <h3>Zagadnienia poddane pod głosowanie</h3>
      {questions}
    </div>
    <div class="deadline">Termin głosowania: <strong>do {date_end}</strong></div>
    <div class="cta">
      <a href="{vote_url}">Przejdź do głosowania →</a>
    </div>
    <p>Każdy głos ma znaczenie. Dziękujemy za zaangażowanie.</p>
  </div>
  <div class="footer">
    © {brand_short} &nbsp;|&nbsp; Wiadomość wygenerowana automatycznie<br><br>
    <span style="font-size:12px;color:#bbb">
      Głosowanie przeprowadzono na stronie <a href="{site_url}" style="color:#bbb">{site_url}</a><br>
      System: <em>Otwarte Głosowanie (Open Vote)</em> &mdash; autor: Tomasz Kalinowski &mdash; <a href="https://github.com/t7jk/openvote" style="color:#bbb">kod źródłowy na GitHub</a>
    </span>
  </div>
</div>
