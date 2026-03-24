# RC1 Prompt Assembly Script for Claude Opus 4.6
# Usage: .\scripts\assemble-rc1.ps1 -Run 1|2|3
# Output: assembled prompt saved to ./tmp/rc1-runN-assembled.md

param(
    [Parameter(Mandatory=$true)]
    [ValidateSet("1","2","3")]
    [string]$Run
)

$ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$ArtifactDir = "$env:USERPROFILE\.gemini\antigravity\brain\b632c126-2f0c-4ce4-ae28-b29f592a121e"
$OutputDir = Join-Path $ProjectRoot "tmp"

if (-not (Test-Path $OutputDir)) { New-Item -ItemType Directory -Path $OutputDir | Out-Null }

function Read-FileContent($path) {
    $fullPath = Join-Path $ProjectRoot $path
    if (Test-Path $fullPath) {
        return Get-Content $fullPath -Raw
    } else {
        return "<!-- FILE NOT FOUND: $path -->"
    }
}

function Read-First-Last($path, $n) {
    $fullPath = Join-Path $ProjectRoot $path
    if (Test-Path $fullPath) {
        $lines = Get-Content $fullPath
        $first = ($lines | Select-Object -First $n) -join "`n"
        $last = ($lines | Select-Object -Last $n) -join "`n"
        return "$first`n`n---separator (line $($lines.Count) total)---`n`n$last"
    }
    return "<!-- FILE NOT FOUND: $path -->"
}

function Read-AllInDir($dir, $pattern) {
    $fullDir = Join-Path $ProjectRoot $dir
    $result = ""
    if (Test-Path $fullDir) {
        Get-ChildItem $fullDir -Filter $pattern | ForEach-Object {
            $result += "`n---`n### $($_.Name)`n`n"
            $result += Get-Content $_.FullName -Raw
        }
    }
    return $result
}

Write-Host "Assembling RC1 Run $Run prompt..." -ForegroundColor Cyan

switch ($Run) {
    "1" {
        $template = Get-Content "$ArtifactDir\rc1_run1_r1.md" -Raw
        
        $replacements = @{
            '[PASTE FULL CONTENT OF foundation/00-master-contract.md HERE]' = Read-FileContent 'foundation/00-master-contract.md'
            '[PASTE FULL CONTENT OF docs/cleanup/unresolved-registry.md HERE]' = Read-FileContent 'docs/cleanup/unresolved-registry.md'
            '[PASTE FULL CONTENT OF docs/cleanup/00-inventory.md HERE]' = Read-FileContent 'docs/cleanup/00-inventory.md'
            '[PASTE FULL CONTENT OF docs/COMPACT.md HERE]' = Read-FileContent 'docs/COMPACT.md'
            '[PASTE FIRST 30 LINES OF docs/WORKLOG.md HERE]' = ((Get-Content (Join-Path $ProjectRoot 'docs/WORKLOG.md') | Select-Object -First 30) -join "`n")
            '[PASTE LAST 30 LINES OF docs/WORKLOG.md HERE]' = ((Get-Content (Join-Path $ProjectRoot 'docs/WORKLOG.md') | Select-Object -Last 30) -join "`n")
            '[PASTE FULL CONTENT OF mcp/soleil-mcp/policy.json HERE]' = Read-FileContent 'mcp/soleil-mcp/policy.json'
            '[PASTE FULL CONTENT OF docs/DEVELOPMENT_HOOKS.md HERE]' = Read-FileContent 'docs/DEVELOPMENT_HOOKS.md'
        }
        
        foreach ($key in $replacements.Keys) {
            $template = $template.Replace($key, $replacements[$key])
        }
        
        $outFile = Join-Path $OutputDir "rc1-run1-assembled.md"
        $template | Set-Content $outFile -Encoding UTF8
        $chars = $template.Length
        Write-Host "Run 1 assembled: $outFile ($chars chars, ~$([math]::Round($chars/4)) tokens)" -ForegroundColor Green
    }
    
    "2" {
        $template = Get-Content "$ArtifactDir\rc1_run2_r2.md" -Raw
        
        # Master contract
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF foundation/00-master-contract.md HERE]',
            (Read-FileContent 'foundation/00-master-contract.md')
        )
        
        # Registry (user should use post-R1 version if available)
        $registryPath = Join-Path $OutputDir "rc1-run1-registry-output.md"
        if (Test-Path $registryPath) {
            $registry = Get-Content $registryPath -Raw
        } else {
            $registry = Read-FileContent 'docs/cleanup/unresolved-registry.md'
            Write-Host "WARNING: Using original registry. For best results, paste the RC1-A output registry." -ForegroundColor Yellow
        }
        $template = $template.Replace(
            '[PASTE THE UPDATED docs/cleanup/unresolved-registry.md FROM RC1-A OUTPUT HERE]',
            $registry
        )
        
        # Commands
        @('fix-backend','fix-frontend','review-pr','ship','sync-docs','audit-security') | ForEach-Object {
            $template = $template.Replace(
                "[PASTE FULL CONTENT OF .claude/commands/$_.md HERE]",
                (Read-FileContent ".claude/commands/$_.md")
            )
        }
        
        # Skills
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF ALL skills/laravel/*.md FILES HERE]',
            (Read-AllInDir 'skills/laravel' '*.md')
        )
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF ALL skills/react/*.md FILES HERE]',
            (Read-AllInDir 'skills/react' '*.md')
        )
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF ALL skills/ops/*.md FILES HERE]',
            (Read-AllInDir 'skills/ops' '*.md')
        )
        
        # Agents
        @('docs-sync','frontend-reviewer','security-reviewer','db-investigator') | ForEach-Object {
            $template = $template.Replace(
                "[PASTE FULL CONTENT OF .claude/agents/$_.md HERE]",
                (Read-FileContent ".claude/agents/$_.md")
            )
        }
        
        # MCP
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF mcp/soleil-mcp/policy.json HERE]',
            (Read-FileContent 'mcp/soleil-mcp/policy.json')
        )
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF mcp/soleil-mcp/readme.md HERE]',
            (Read-FileContent 'mcp/soleil-mcp/readme.md')
        )
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF docs/MCP.md HERE]',
            (Read-FileContent 'docs/MCP.md')
        )
        
        # Command-skill map
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF docs/cleanup/05-command-skill-map.md HERE]',
            (Read-FileContent 'docs/cleanup/05-command-skill-map.md')
        )
        
        $outFile = Join-Path $OutputDir "rc1-run2-assembled.md"
        $template | Set-Content $outFile -Encoding UTF8
        $chars = $template.Length
        Write-Host "Run 2 assembled: $outFile ($chars chars, ~$([math]::Round($chars/4)) tokens)" -ForegroundColor Green
    }
    
    "3" {
        $template = Get-Content "$ArtifactDir\rc1_run3_r3_gate.md" -Raw
        
        # Master contract
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF foundation/00-master-contract.md HERE]',
            (Read-FileContent 'foundation/00-master-contract.md')
        )
        
        # Registry (post-R2)
        $registryPath = Join-Path $OutputDir "rc1-run2-registry-output.md"
        if (Test-Path $registryPath) {
            $registry = Get-Content $registryPath -Raw
        } else {
            $registry = Read-FileContent 'docs/cleanup/unresolved-registry.md'
            Write-Host "WARNING: Using original registry. For best results, paste the RC1-B output registry." -ForegroundColor Yellow
        }
        $template = $template.Replace(
            '[PASTE THE UPDATED docs/cleanup/unresolved-registry.md FROM RC1-B OUTPUT HERE]',
            $registry
        )
        
        # Agent files for handoff
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF .claude/agents/frontend-reviewer.md HERE]',
            (Read-FileContent '.claude/agents/frontend-reviewer.md')
        )
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF .claude/agents/security-reviewer.md HERE]',
            (Read-FileContent '.claude/agents/security-reviewer.md')
        )
        
        # Rule files
        @('booking-integrity','auth-token-safety','migration-safety') | ForEach-Object {
            $template = $template.Replace(
                "[PASTE FULL CONTENT OF .agent/rules/$_.md HERE]",
                (Read-FileContent ".agent/rules/$_.md")
            )
        }
        
        # docs-sync
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF .claude/agents/docs-sync.md HERE]',
            (Read-FileContent '.claude/agents/docs-sync.md')
        )
        
        # DB facts
        $template = $template.Replace(
            '[PASTE FULL CONTENT OF docs/DB_FACTS.md HERE]',
            (Read-FileContent 'docs/DB_FACTS.md')
        )
        
        # Architecture facts (leave placeholder — user should paste relevant sections)
        # This one is too large to auto-paste fully
        
        $outFile = Join-Path $OutputDir "rc1-run3-assembled.md"
        $template | Set-Content $outFile -Encoding UTF8
        $chars = $template.Length
        Write-Host "Run 3 assembled: $outFile ($chars chars, ~$([math]::Round($chars/4)) tokens)" -ForegroundColor Green
        Write-Host "NOTE: You still need to paste ARCHITECTURE_FACTS.md relevant sections manually." -ForegroundColor Yellow
    }
}

Write-Host "`nAssembly complete. Review the file, then:" -ForegroundColor Cyan
Write-Host "  Get-Content $OutputDir\rc1-run$Run-assembled.md | claude" -ForegroundColor White
