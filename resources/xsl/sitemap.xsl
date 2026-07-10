<?xml version="1.0" encoding="UTF-8"?>
<!--
  Rankbeam styled sitemap stylesheet.

  Referenced from generated sitemaps via an <?xml-stylesheet?> processing
  instruction so a browser renders them as a readable, branded page instead of
  raw XML. Search engines ignore the instruction entirely — the sitemap stays a
  machine-readable XML document.

  Security: every value that comes from the sitemap (loc, lastmod, …) is emitted
  through <xsl:value-of> or an attribute value template, both of which escape by
  the XSLT spec. Output escaping is never switched off anywhere in this file, and
  a loc is only turned into a clickable <a> when it is an http(s) URL — so a
  javascript:/data: loc can never become a live link. See the "must not introduce
  XML/HTML injection via URL contents" requirement (research §3.5).
-->
<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
    xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"
    xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
    xmlns:xhtml="http://www.w3.org/1999/xhtml"
    exclude-result-prefixes="s image video news xhtml">

  <xsl:output method="html" encoding="UTF-8" indent="yes" doctype-system="about:legacy-compat"/>

  <!-- ================================================================== -->
  <!-- Shared page chrome                                                  -->
  <!-- ================================================================== -->
  <xsl:template name="head">
    <xsl:param name="title"/>
    <head>
      <meta charset="UTF-8"/>
      <meta name="viewport" content="width=device-width, initial-scale=1"/>
      <meta name="robots" content="noindex,follow"/>
      <title><xsl:value-of select="$title"/></title>
      <style>
        :root {
          --rb-accent: #3D5AFE;
          --rb-accent-soft: rgba(61, 90, 254, 0.10);
          --rb-bg: #f7f8fb;
          --rb-card: #ffffff;
          --rb-text: #1a1d29;
          --rb-muted: #6b7280;
          --rb-border: #e6e8ef;
          --rb-row: #ffffff;
          --rb-row-alt: #fafbfe;
          --rb-warn-bg: #fff7ed;
          --rb-warn-border: #fdba74;
          --rb-warn-text: #9a3412;
          --rb-badge-bg: #eef1ff;
        }
        @media (prefers-color-scheme: dark) {
          :root {
            --rb-accent: #7c8cff;
            --rb-accent-soft: rgba(124, 140, 255, 0.14);
            --rb-bg: #0f1117;
            --rb-card: #161923;
            --rb-text: #e7e9f0;
            --rb-muted: #9aa1b1;
            --rb-border: #262b38;
            --rb-row: #161923;
            --rb-row-alt: #1b1f2b;
            --rb-warn-bg: #2a1c10;
            --rb-warn-border: #7c4a1e;
            --rb-warn-text: #fdba74;
            --rb-badge-bg: #202641;
          }
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
          margin: 0;
          background: var(--rb-bg);
          color: var(--rb-text);
          font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                Helvetica, Arial, sans-serif;
          padding: 0 16px 48px;
        }
        .wrap { max-width: 1080px; margin: 0 auto; }
        header {
          display: flex; align-items: center; gap: 14px;
          padding: 28px 0 20px;
        }
        .mark {
          width: 34px; height: 34px; border-radius: 9px;
          background: var(--rb-accent);
          display: inline-flex; align-items: center; justify-content: center;
          color: #fff; font-weight: 700; font-size: 18px; flex: none;
          box-shadow: 0 2px 8px var(--rb-accent-soft);
        }
        .titles h1 { margin: 0; font-size: 20px; font-weight: 650; letter-spacing: -0.01em; }
        .titles p { margin: 2px 0 0; color: var(--rb-muted); font-size: 13px; }
        .stats {
          display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 18px;
        }
        .stat {
          background: var(--rb-card); border: 1px solid var(--rb-border);
          border-radius: 10px; padding: 10px 14px; min-width: 92px;
        }
        .stat .n { font-size: 20px; font-weight: 650; }
        .stat .l { font-size: 12px; color: var(--rb-muted); }
        .note {
          background: var(--rb-accent-soft); border: 1px solid transparent;
          border-radius: 10px; padding: 11px 14px; margin: 0 0 16px;
          font-size: 13px; color: var(--rb-text);
        }
        .note a { color: var(--rb-accent); }
        .warn {
          background: var(--rb-warn-bg); border: 1px solid var(--rb-warn-border);
          color: var(--rb-warn-text); border-radius: 10px;
          padding: 11px 14px; margin: 0 0 16px; font-size: 13px;
        }
        .warn strong { font-weight: 650; }
        .table-wrap {
          background: var(--rb-card); border: 1px solid var(--rb-border);
          border-radius: 12px; overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        thead th {
          position: sticky; top: 0; z-index: 1;
          background: var(--rb-card); text-align: left;
          padding: 12px 14px; font-weight: 600; color: var(--rb-muted);
          border-bottom: 1px solid var(--rb-border); white-space: nowrap;
        }
        tbody td { padding: 11px 14px; border-bottom: 1px solid var(--rb-border); vertical-align: top; }
        tbody tr:nth-child(odd) { background: var(--rb-row); }
        tbody tr:nth-child(even) { background: var(--rb-row-alt); }
        tbody tr:last-child td { border-bottom: 0; }
        td.idx { color: var(--rb-muted); font-variant-numeric: tabular-nums; width: 1%; white-space: nowrap; }
        td.loc { word-break: break-all; }
        td.loc a { color: var(--rb-accent); text-decoration: none; }
        td.loc a:hover { text-decoration: underline; }
        td.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .muted { color: var(--rb-muted); }
        .badge {
          display: inline-block; margin-left: 6px; padding: 1px 7px;
          border-radius: 999px; font-size: 11px; font-weight: 600;
          background: var(--rb-badge-bg); color: var(--rb-accent);
          vertical-align: middle;
        }
        .badge.flag {
          background: var(--rb-warn-bg); color: var(--rb-warn-text);
          border: 1px solid var(--rb-warn-border);
        }
        footer {
          margin: 22px 4px 0; font-size: 12.5px; color: var(--rb-muted);
          display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;
        }
        footer a { color: var(--rb-accent); text-decoration: none; font-weight: 600; }
        @media (max-width: 640px) {
          .titles h1 { font-size: 18px; }
          thead th, tbody td { padding: 10px; }
        }
      </style>
    </head>
  </xsl:template>

  <xsl:template name="brand-header">
    <xsl:param name="subtitle"/>
    <header>
      <span class="mark">R</span>
      <div class="titles">
        <h1>Rankbeam sitemap</h1>
        <p><xsl:value-of select="$subtitle"/></p>
      </div>
    </header>
  </xsl:template>

  <xsl:template name="footer">
    <footer>
      <span>A machine-readable XML sitemap, styled for humans.</span>
      <span>Generated by <a href="https://rankbeam.dev" rel="noopener noreferrer">Rankbeam</a></span>
    </footer>
  </xsl:template>

  <!-- ================================================================== -->
  <!-- Sitemap index (<sitemapindex>)                                     -->
  <!-- ================================================================== -->
  <xsl:template match="/s:sitemapindex">
    <html lang="en">
      <xsl:call-template name="head">
        <xsl:with-param name="title" select="'XML Sitemap Index'"/>
      </xsl:call-template>
      <body>
        <div class="wrap">
          <xsl:call-template name="brand-header">
            <xsl:with-param name="subtitle" select="'Sitemap index'"/>
          </xsl:call-template>

          <div class="stats">
            <div class="stat">
              <div class="n"><xsl:value-of select="count(s:sitemap)"/></div>
              <div class="l">sitemaps</div>
            </div>
          </div>

          <div class="note">
            This is a <strong>sitemap index</strong> — a list of the individual
            sitemaps for this site. Learn more at
            <a href="https://www.sitemaps.org/protocol.html" rel="noopener noreferrer">sitemaps.org</a>.
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Sitemap</th>
                  <th>Last modified</th>
                </tr>
              </thead>
              <tbody>
                <xsl:for-each select="s:sitemap">
                  <tr>
                    <td class="idx"><xsl:value-of select="position()"/></td>
                    <td class="loc">
                      <xsl:call-template name="loc-cell">
                        <xsl:with-param name="loc" select="s:loc"/>
                      </xsl:call-template>
                    </td>
                    <td class="num">
                      <xsl:call-template name="lastmod-cell">
                        <xsl:with-param name="lastmod" select="s:lastmod"/>
                      </xsl:call-template>
                    </td>
                  </tr>
                </xsl:for-each>
              </tbody>
            </table>
          </div>

          <xsl:call-template name="footer"/>
        </div>
      </body>
    </html>
  </xsl:template>

  <!-- ================================================================== -->
  <!-- URL set (<urlset>)                                                 -->
  <!-- ================================================================== -->
  <xsl:template match="/s:urlset">
    <!-- Validation counts (research §2.7). Non-absolute = a loc that is not an
         http(s) URL; missing lastmod = the freshness signal Google discounts
         when absent (edge-case warning #6 — we flag it, never fabricate it). -->
    <xsl:variable name="missingLastmod"
        select="count(s:url[not(s:lastmod) or normalize-space(s:lastmod) = ''])"/>
    <xsl:variable name="nonAbsolute"
        select="count(s:url[not(starts-with(s:loc, 'http://') or starts-with(s:loc, 'https://'))])"/>

    <html lang="en">
      <xsl:call-template name="head">
        <xsl:with-param name="title" select="'XML Sitemap'"/>
      </xsl:call-template>
      <body>
        <div class="wrap">
          <xsl:call-template name="brand-header">
            <xsl:with-param name="subtitle" select="'URL sitemap'"/>
          </xsl:call-template>

          <div class="stats">
            <div class="stat">
              <div class="n"><xsl:value-of select="count(s:url)"/></div>
              <div class="l">URLs</div>
            </div>
            <div class="stat">
              <div class="n"><xsl:value-of select="count(s:url/image:image)"/></div>
              <div class="l">images</div>
            </div>
            <div class="stat">
              <div class="n"><xsl:value-of select="count(s:url/xhtml:link[@rel = 'alternate'])"/></div>
              <div class="l">alternates</div>
            </div>
          </div>

          <div class="note">
            This is an <strong>XML sitemap</strong>, generated for search engines.
            Read more about the format at
            <a href="https://www.sitemaps.org/protocol.html" rel="noopener noreferrer">sitemaps.org</a>.
          </div>

          <xsl:if test="$missingLastmod &gt; 0 or $nonAbsolute &gt; 0">
            <div class="warn">
              <strong>Validation notes:</strong>
              <xsl:if test="$nonAbsolute &gt; 0">
                <xsl:text> </xsl:text>
                <xsl:value-of select="$nonAbsolute"/>
                <xsl:text> URL(s) are not absolute http(s) URLs.</xsl:text>
              </xsl:if>
              <xsl:if test="$missingLastmod &gt; 0">
                <xsl:text> </xsl:text>
                <xsl:value-of select="$missingLastmod"/>
                <xsl:text> URL(s) are missing a </xsl:text><code>lastmod</code><xsl:text> date.</xsl:text>
              </xsl:if>
            </div>
          </xsl:if>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>URL</th>
                  <th>Last modified</th>
                  <th>Change freq.</th>
                  <th>Priority</th>
                  <th>Images</th>
                  <th>Alternates</th>
                </tr>
              </thead>
              <tbody>
                <xsl:for-each select="s:url">
                  <tr>
                    <td class="idx"><xsl:value-of select="position()"/></td>
                    <td class="loc">
                      <xsl:call-template name="loc-cell">
                        <xsl:with-param name="loc" select="s:loc"/>
                      </xsl:call-template>
                    </td>
                    <td class="num">
                      <xsl:call-template name="lastmod-cell">
                        <xsl:with-param name="lastmod" select="s:lastmod"/>
                      </xsl:call-template>
                    </td>
                    <td class="num">
                      <xsl:choose>
                        <xsl:when test="s:changefreq"><xsl:value-of select="s:changefreq"/></xsl:when>
                        <xsl:otherwise><span class="muted">—</span></xsl:otherwise>
                      </xsl:choose>
                    </td>
                    <td class="num">
                      <xsl:choose>
                        <xsl:when test="s:priority"><xsl:value-of select="s:priority"/></xsl:when>
                        <xsl:otherwise><span class="muted">—</span></xsl:otherwise>
                      </xsl:choose>
                    </td>
                    <td class="num"><xsl:value-of select="count(image:image)"/></td>
                    <td class="num"><xsl:value-of select="count(xhtml:link[@rel = 'alternate'])"/></td>
                  </tr>
                </xsl:for-each>
              </tbody>
            </table>
          </div>

          <xsl:call-template name="footer"/>
        </div>
      </body>
    </html>
  </xsl:template>

  <!-- ================================================================== -->
  <!-- Cell helpers                                                        -->
  <!-- ================================================================== -->

  <!-- A loc cell. Only http(s) locs become clickable links; anything else is
       rendered as escaped text with a flag, so a javascript:/data: loc can
       never become a live link. value-of / the href AVT both escape. -->
  <xsl:template name="loc-cell">
    <xsl:param name="loc"/>
    <xsl:choose>
      <xsl:when test="starts-with($loc, 'http://') or starts-with($loc, 'https://')">
        <a href="{$loc}" rel="noopener noreferrer nofollow"><xsl:value-of select="$loc"/></a>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="$loc"/>
        <span class="badge flag">non-absolute</span>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- A lastmod cell. Missing/empty dates are flagged, never invented. -->
  <xsl:template name="lastmod-cell">
    <xsl:param name="lastmod"/>
    <xsl:choose>
      <xsl:when test="$lastmod and normalize-space($lastmod) != ''">
        <xsl:value-of select="$lastmod"/>
      </xsl:when>
      <xsl:otherwise>
        <span class="muted">—</span>
        <span class="badge flag">no lastmod</span>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

</xsl:stylesheet>
