import json, math, re, unicodedata

adm1 = json.load(open('pry_adm1.geojson'))
adm2 = json.load(open('pry_adm2.geojson'))
dep = next(f for f in adm1['features'] if f['properties']['shapeName'] == 'CAAGUAZU')

def rings_of(geom):
    out = []
    polys = geom['coordinates'] if geom['type']=='MultiPolygon' else [geom['coordinates']]
    if geom['type']=='Polygon': polys=[geom['coordinates']]
    for poly in (geom['coordinates'] if geom['type']=='MultiPolygon' else [geom['coordinates']]):
        for ring in poly:
            out.append([(c[0],c[1]) for c in ring])
    return out
dep_rings = rings_of(dep['geometry'])

def pir(x,y,rings):
    inside=False
    for ring in rings:
        n=len(ring); j=n-1
        for i in range(n):
            xi,yi=ring[i]; xj,yj=ring[j]
            if ((yi>y)!=(yj>y)) and (x<(xj-xi)*(y-yi)/(yj-yi)+xi): inside=not inside
            j=i
    return inside

def area_centroid(ring):
    a=cx=cy=0.0; n=len(ring)
    for i in range(n):
        x0,y0=ring[i]; x1,y1=ring[(i+1)%n]
        cr=x0*y1-x1*y0; a+=cr; cx+=(x0+x1)*cr; cy+=(y0+y1)*cr
    if a==0:
        xs=[p[0] for p in ring]; ys=[p[1] for p in ring]; return sum(xs)/len(xs),sum(ys)/len(ys)
    a*=0.5; return cx/(6*a),cy/(6*a)

def rep_point(geom):
    if geom['type']=='Polygon': outer=geom['coordinates'][0]
    else: outer=max((p[0] for p in geom['coordinates']),key=len)
    return area_centroid([(c[0],c[1]) for c in outer])

sel=[f for f in adm2['features'] if pir(*rep_point(f['geometry']),dep_rings)]

# bbox + proyección
def all_c(feats):
    for f in feats:
        g=f['geometry']
        for poly in (g['coordinates'] if g['type']=='MultiPolygon' else [g['coordinates']]):
            for ring in poly:
                for c in ring: yield c[0],c[1]
xs=[c[0] for c in all_c(sel)]; ys=[c[1] for c in all_c(sel)]
minlng,maxlng=min(xs),max(xs); minlat,maxlat=min(ys),max(ys)
k=math.cos(math.radians((minlat+maxlat)/2))
W=1000.0; scale=W/((maxlng-minlng)*k); H=(maxlat-minlat)*scale
def proj(lng,lat): return ((lng-minlng)*k*scale,(maxlat-lat)*scale)

# Douglas-Peucker en pixeles
def dp(points,eps):
    if len(points)<3: return points
    dmax=0.0; idx=0
    a=points[0]; b=points[-1]
    ax,ay=a; bx,by=b; dx=bx-ax; dy=by-ay
    norm=math.hypot(dx,dy) or 1e-12
    for i in range(1,len(points)-1):
        px,py=points[i]
        d=abs(dy*px-dx*py+bx*ay-by*ax)/norm
        if d>dmax: dmax=d; idx=i
    if dmax>eps:
        return dp(points[:idx+1],eps)[:-1]+dp(points[idx:],eps)
    return [a,b]

def simplify_ring(ring,eps=0.7):
    pts=[proj(c[0],c[1]) for c in ring]
    if len(pts)>1 and pts[0]==pts[-1]:
        pts=pts[:-1]            # abrir el anillo (DP degenera si inicio==fin)
    if len(pts)<4:
        s=pts[:]
    else:
        s=dp(pts,eps)
    s.append(s[0])             # re-cerrar
    return s

def fmt(v): 
    r=round(v,1); return ('%g'%r)
def geom_to_path(geom):
    d=[]
    for poly in (geom['coordinates'] if geom['type']=='MultiPolygon' else [geom['coordinates']]):
        for ring in poly:
            s=simplify_ring(ring)
            d.append("M"+" ".join(f"{fmt(x)},{fmt(y)}" for x,y in s)+"Z")
    return "".join(d)


NAME_MAP={
 "3 De Febrero":"3 de Febrero","Caaguazu":"Caaguazú","Carayao":"Carayaó",
 "Cecilio Baez":"Cecilio Báez","Coronel Oviedo":"Coronel Oviedo",
 "Dr. Juan Manuel Frutos":"Dr. Juan Manuel Frutos (Pastoreo)",
 "J Eulogio Estigarribia":"Dr. J. Eulogio Estigarribia (Campo 9)",
 "Jose Domingo Ocampos":"José Domingo Ocampos","La Pastora":"La Pastora",
 "Mcal. Francisco Solano Lopez":"Mcal. Francisco Solano López",
 "Nueva Londres":"Nueva Londres","Nueva Toledo":"Nueva Toledo",
 "R.I. 3 Corrales":"R.I. 3 Corrales","Raul Arsenio Oviedo":"Raúl Arsenio Oviedo",
 "Repatriacion":"Repatriación","San Joaquin":"San Joaquín",
 "San Jose De Los Arroyos":"San José de los Arroyos",
 "Santa Rosa Del Mbutuy":"Santa Rosa del Mbutuy","Simon Bolivar":"Simón Bolívar",
 "Tembiapora":"Tembiaporã","Vaqueria":"Vaquería","Yhu":"Yhú",
}
def nice(n): return NAME_MAP.get(n,n)

def slug(name):
    s=unicodedata.normalize('NFKD',name).encode('ascii','ignore').decode()
    return re.sub(r'[^a-zA-Z0-9]+','-',s).strip('-').lower()

parts=[f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W:.0f} {H:.2f}" width="{W:.0f}" height="{H:.2f}" data-name="Caaguazu" data-title="Departamento de Caaguazú">',
 '<style>path{fill:#e8e2d4;stroke:#155c33;stroke-width:0.8;stroke-linejoin:round;vector-effect:non-scaling-stroke}path:hover{fill:#1f7a44}</style>',
 '<g id="caaguazu-distritos">']
for f in sorted(sel,key=lambda f:nice(f['properties']['shapeName'])):
    name=f['properties']['shapeName']; sid=slug(name); disp=nice(name)
    import xml.sax.saxutils as su; t=su.escape(disp)
    parts.append(f'<path id="{sid}" title="{t}" data-title="{t}" d="{geom_to_path(f["geometry"])}"><title>{t}</title></path>')
parts.append('</g></svg>')
svg="\n".join(parts)
open('/tmp/caaguazu-distritos.svg','w').write(svg)
print("Distritos:",len(sel),"| SVG KB:",round(len(svg)/1024,1),"| viewBox 0 0 %.0f %.2f"%(W,H))
